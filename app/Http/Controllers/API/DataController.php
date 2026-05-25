<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Buku;
use App\Models\DetailPublikasi;
use App\Models\Fakultas;
use App\Models\FilePublikasi;
use App\Models\JenisPublikasi;
use App\Models\Jurnal;
use App\Models\Karya;
use App\Models\KategoriKegiatan;
use App\Models\NonPublish;
use App\Models\Opini;
use App\Models\Paten;
use App\Models\Penulis;
use App\Models\Poster;
use App\Models\Prodi;
use App\Models\Profile;
use App\Models\Prosiding;
use App\Models\Publikasi;
use App\Models\Seminar;
use App\Models\User;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\PseudoTypes\LowercaseString;

class DataController extends Controller
{
    public function tahun()
    {
        $data = Publikasi::selectRaw('year(tanggal) as tahun')
            ->groupByRaw('tahun')
            ->orderByDesc('tahun')
            ->pluck('tahun');
        return response()->json($data, 200);
    }

    public function fakultas()
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->withToken(config('services.sdm.key'))
            ->get(config('services.sdm.url') . '/unit-kerja/flat?jenis=fakultas');
        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal mengambil data fakultas',
            ], 502);
        }
        $data = collect($response->json()['data'])->filter(function ($item) {
            if (strpos($item['nama_unit'], 'Dekanat') !== false || strpos($item['nama_unit'], 'Tata') !== false) {
                return false;
            }
            return true;
        })->values();
        return response()->json($data, 200);
    }
    public function syncFakultas()
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->withToken(config('services.sdm.key'))
            ->get(config('services.sdm.url') . '/unit-kerja/flat?jenis=fakultas');
        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal mengambil data fakultas',
            ], 502);
        }
        collect($response->json()['data'])->filter(function ($item) {
            if (strpos($item['nama_unit'], 'Dekanat') !== false || strpos($item['nama_unit'], 'Tata') !== false) {
                return false;
            }
            Fakultas::firstOrCreate([
                'nama' => $item['nama_unit'],
                'id_unit' => $item['id_unit']
            ]);
        })->values();
    }
    public function syncProdi()
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->withToken(config('services.sdm.key'))
            ->get(config('services.sdm.url') . '/unit-kerja/flat?jenis=prodi');
        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal mengambil data prodi',
            ], 502);
        }
        collect($response->json()['data'])->filter(function ($item) {
            Prodi::firstOrCreate([
                'nama' => $item['nama_unit'],
                'id_unit' => $item['id_unit'],
                'fakultas_id' => Fakultas::where('id_unit', $item['id_unit_induk'])->first()->id
            ]);
        })->values();
    }
    public function prodi($fakultas_id)
    {
        if (!$fakultas_id) {
            return [];
        }
        $response = Http::acceptJson()
            ->timeout(20)
            ->withToken(config('services.sdm.key'))
            ->get(config('services.sdm.url') . '/unit-kerja/flat?jenis=prodi');
        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal mengambil data prodi',
            ], 502);
        }
        $data = collect($response->json()['data'])->filter(function ($item) use ($fakultas_id) {
            return $item['id_unit_induk'] == $fakultas_id;
        })->values();
        return response()->json($data, 200);
    }
    public function jenisPublikasi()
    {
        $keywords = ['Jurnal', 'Prosiding'];

        $data = JenisPublikasi::where(function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $query->orWhere('nama', 'like', $keyword . '%');
            }
        })
            ->orderBy('nama')
            ->get();

        return response()->json($data);
    }
    public function dataFSD(Request $request, $alias)
    {
        $configuredKey = config('services.publication_api.key');
        $requestKey = $request->header('X-API-KEY') ?: $request->bearerToken();

        if (!$configuredKey) {
            return response()->json([
                'message' => 'PUBLICATION_API_KEY belum dikonfigurasi.',
            ], 500);
        }

        if (!$requestKey || !hash_equals((string) $configuredKey, (string) $requestKey)) {
            return response()->json([
                'message' => 'API key tidak valid.',
            ], 401);
        }
        $cacheKey = 'data_fsd_' . $alias;
        $cacheDuration = 60 * 24; // 24 hours in minutes

        // $data = Cache::remember($cacheKey, $cacheDuration, function () use ($alias) {
        $user = User::where('alias', $alias)->first();

        if (!$user || !$user->id_sdm) {
            return [];
        }

        $idSdm = $user->id_sdm;

        $data = Publikasi::with([
            'jenisPublikasi' => function ($query) {
                $query->where(function ($q) {
                    $q->where('nama', 'like', 'Jurnal%')
                        ->orWhere('nama', 'like', 'Prosiding%');
                });
            },
            'detailPublikasi:publikasi_id,quartile'
        ])
            ->whereHas('penulis', function ($query) use ($idSdm) {
                $query->where('id_sdm', $idSdm);
            })
            ->select('id', 'tanggal', 'jenis_publikasi_id', 'quartile')
            ->orderByDesc('tanggal')
            ->get()
            ->map(function ($item) {
                $item->tahun = $item->tanggal
                    ? Carbon::parse($item->tanggal)->year
                    : null;

                $item->tipe = optional($item->jenisPublikasi)->nama;

                return $item;
            });
        // });

        return response()->json($data, 200);
    }
    public function index()
    {
        $idSdm = auth()->user()->id_sdm;

        if (!$idSdm) {
            return response()->json([], 200);
        }

        $data = Publikasi::with([
            'jenisPublikasi' => function ($query) {
                $query->where(function ($q) {
                    $q->where('nama', 'like', 'Jurnal%')
                        ->orWhere('nama', 'like', 'Prosiding%');
                });
            },
            'detailPublikasi',
            'penulis'
        ])
            ->whereHas('penulis', function ($query) use ($idSdm) {
                $query->where('id_sdm', $idSdm);
            })
            ->orderByDesc('tanggal')
            ->get()
            ->map(function ($item) {
                $item->tahun = $item->tanggal ? Carbon::parse($item->tanggal)->year : null;
                $item->tipe = optional($item->jenisPublikasi)->nama;

                return $item;
            });

        return response()->json($data, 200);
    }
    public function file(Request $request)
    {
        $fileName = '';
        if ($request->hasFile('file')) {
            $fileName = $request->file->store('public/file/' . $request->tipe . '/' . $request->column);
            $fileName = str_replace("public/", "", $fileName);
            FilePublikasi::create([
                'table_id' => $request->table_id,
                'publikasi' => $request->tipe,
                'jenis' => $request->column,
                'file' => $fileName
            ]);
            return response()->json([
                'message' => 'success'
            ], 200);
        }
    }

    public function sync(Request $request)
    {
        $user = $request->user();
        $baseUrl = config('services.sister.url');
        $idSdm = $user->id_sdm;

        if (!$baseUrl) {
            return response()->json([
                'message' => 'Konfigurasi SISTER_API_URL belum tersedia.',
            ], 422);
        }

        if (!$idSdm) {
            return response()->json([
                'message' => 'id_sdm belum tersedia untuk akun ini. Silakan login ulang agar data SISTER dilengkapi.',
            ], 422);
        }

        $token = $this->getSisterToken();
        if (!$token) {
            return response()->json([
                'message' => 'Tidak dapat mengambil token SISTER.',
            ], 502);
        }

        try {
            $response = $this->requestSisterPublikasi($baseUrl, $token, $idSdm);

            if ($response->status() === 401) {
                Cache::forget('sister_api_token');
                $token = $this->getSisterToken();

                if ($token) {
                    $response = $this->requestSisterPublikasi($baseUrl, $token, $idSdm);
                }
            }
        } catch (\Throwable $e) {
            Log::error('SISTER publikasi sync failed.', [
                'user_id' => $user->id,
                'id_sdm' => $idSdm,
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Sync publikasi SISTER gagal diproses.',
            ], 502);
        }

        if (!$response->successful()) {
            Log::warning('SISTER publikasi returned an error.', [
                'user_id' => $user->id,
                'id_sdm' => $idSdm,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'SISTER gagal merespons sync publikasi.',
                'status' => $response->status(),
            ], 502);
        }

        $records = $this->extractSisterRecords($response->json());
        $result = $this->storeSisterPublikasi($records, $baseUrl, $token);

        return response()->json([
            'message' => 'Sync publikasi SISTER selesai.',
            'configured' => true,
            'data' => [
                'user_id' => $user->id,
                'id_sdm' => $idSdm,
                'received' => count($records),
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'detail_updated' => $result['detail_updated'],
                'penulis_inserted' => $result['penulis_inserted'],
                'penulis_skipped' => $result['penulis_skipped'],
                'skipped' => $result['skipped'],
                'counts' => $this->publicationCounts($user),
            ],
        ], 200);
    }

    public function kategoriKegiatanPublikasi()
    {
        $baseUrl = config('services.sister.url');

        if (!$baseUrl) {
            return response()->json([
                'message' => 'Konfigurasi SISTER_API_URL belum tersedia.',
            ], 422);
        }

        $token = $this->getSisterToken();

        if (!$token) {
            return response()->json([
                'message' => 'Tidak dapat mengambil token SISTER.',
            ], 502);
        }

        try {

            $response = $this->requestSisterKategoriKegiatanPublikasi($baseUrl, $token);

            if ($response->status() === 401) {

                Cache::forget('sister_api_token');

                $token = $this->getSisterToken();

                if ($token) {
                    $response = $this->requestSisterKategoriKegiatanPublikasi($baseUrl, $token);
                }
            }

            if (!$response->successful()) {

                Log::warning('SISTER kategori kegiatan publikasi returned an error.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'SISTER gagal merespons referensi kategori kegiatan publikasi.',
                    'status' => $response->status(),
                ], 502);
            }

            $records = $this->extractSisterRecords($response->json());

            $inserted = [];

            foreach ($records as $record) {

                $kategori = KategoriKegiatan::updateOrCreate(
                    ['id' => $record['id']],
                    [
                        'nama' => $record['nama'],
                        'parent_id' => $record['parent_id']
                    ]
                );

                $inserted[] = [
                    'id' => $kategori->id,
                    'nama' => $kategori->nama,
                ];
            }

            return response()->json([
                'message' => 'Referensi kategori kegiatan publikasi berhasil disimpan.',
                'total' => count($inserted),
                'data' => $inserted,
            ]);
        } catch (\Throwable $e) {

            Log::error('SISTER kategori kegiatan publikasi request failed.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal mengambil referensi kategori kegiatan publikasi dari SISTER.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    private function requestSisterPublikasi(string $baseUrl, string $token, string $idSdm)
    {
        $client = Http::acceptJson()
            ->withToken($token)
            ->timeout(60);

        $response = $client->get($baseUrl . '/publikasi/' . $idSdm);

        if (in_array($response->status(), [404, 405])) {
            return $client->get($baseUrl . '/publikasi', [
                'id_sdm' => $idSdm,
            ]);
        }

        return $response;
    }

    private function requestSisterKategoriKegiatanPublikasi(string $baseUrl, string $token)
    {
        return Http::acceptJson()
            ->withToken($token)
            ->timeout(30)
            ->get($baseUrl . '/referensi/kategori_kegiatan', [
                'tipe' => 'list',
                'menu' => 'publikasi',
            ]);
    }

    private function getSisterToken()
    {
        return Cache::remember('sister_api_token', (int) config('services.sister.token_cache_seconds', 3600), function () {
            $baseUrl = config('services.sister.url');
            $username = config('services.sister.username');
            $password = config('services.sister.password');
            $idPengguna = config('services.sister.id_pengguna');

            if (!$baseUrl || !$username || !$password || !$idPengguna) {
                Log::warning('SISTER API credentials are incomplete.');
                return null;
            }

            try {
                $response = Http::acceptJson()
                    ->timeout(20)
                    ->post($baseUrl . '/authorize', [
                        'username' => $username,
                        'password' => $password,
                        'id_pengguna' => $idPengguna,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('SISTER authorize request failed.', [
                    'message' => $e->getMessage(),
                ]);
                return null;
            }

            if (!$response->successful()) {
                Log::warning('SISTER authorize returned an error.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $this->extractSisterToken($response->json());
        });
    }

    private function extractSisterToken($payload)
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (!is_array($payload)) {
            return null;
        }

        $candidates = [
            $payload['token'] ?? null,
            $payload['access_token'] ?? null,
            $payload['data']['token'] ?? null,
            $payload['data']['access_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function extractSisterRecords($payload)
    {
        if (!is_array($payload)) {
            return [];
        }

        $records = $payload['data'] ?? $payload['result'] ?? $payload['publikasi'] ?? $payload;

        if (isset($records['data']) && is_array($records['data'])) {
            $records = $records['data'];
        }

        if (!isset($records[0])) {
            return [];
        }

        return array_values(array_filter($records, 'is_array'));
    }

    private function storeSisterPublikasi(array $records, string $baseUrl, string $token)
    {
        $result = [
            'inserted' => 0,
            'updated' => 0,
            'detail_updated' => 0,
            'penulis_inserted' => 0,
            'penulis_skipped' => 0,
            'skipped' => 0,
        ];

        foreach ($records as $record) {
            $attributes = $this->mapSisterPublikasi($record);

            if (!$attributes) {
                $result['skipped']++;
                continue;
            }

            $exists = Publikasi::where('id', $attributes['id'])->exists();
            Publikasi::updateOrCreate(
                ['id' => $attributes['id']],
                $attributes
            );

            if ($exists) {
                $result['updated']++;
            } else {
                $result['inserted']++;
            }

            $detailResult = $this->syncSisterPublikasiDetail($baseUrl, $token, $attributes['id']);
            $result['detail_updated'] += $detailResult['detail_updated'];
            $result['penulis_inserted'] += $detailResult['penulis_inserted'];
            $result['penulis_skipped'] += $detailResult['penulis_skipped'];
        }

        return $result;
    }

    private function syncSisterPublikasiDetail(string $baseUrl, string $token, string $publikasiId)
    {
        $result = [
            'detail_updated' => 0,
            'penulis_inserted' => 0,
            'penulis_skipped' => 0,
        ];

        try {
            $response = $this->requestSisterPublikasiDetail($baseUrl, $token, $publikasiId);

            if ($response->status() === 401) {
                Cache::forget('sister_api_token');
                $token = $this->getSisterToken();

                if ($token) {
                    $response = $this->requestSisterPublikasiDetail($baseUrl, $token, $publikasiId);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SISTER detail publikasi request failed.', [
                'publikasi_id' => $publikasiId,
                'message' => $e->getMessage(),
            ]);

            return $result;
        }

        if (!$response->successful()) {
            Log::warning('SISTER detail publikasi returned an error.', [
                'publikasi_id' => $publikasiId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $penulis = Penulis::where(['publikasi_id' => $publikasiId, 'id_sdm' => auth()->user()->id_sdm])->first();
            if (!$penulis) {
                Penulis::create([
                    'id' => (string) Str::uuid(),
                    'publikasi_id' => $publikasiId,
                    'id_sdm' => auth()->user()->id_sdm
                ]);
            }
            return $result;
        }

        $detail = $this->extractSisterDetail($response->json());
        if (!$detail) {

            return $result;
        }

        DetailPublikasi::updateOrCreate(
            ['publikasi_id' => $publikasiId],
            $this->mapSisterDetailPublikasi($publikasiId, $detail)
        );
        $result['detail_updated']++;

        foreach ($this->extractSisterPenulis($detail) as $item) {
            $idSdm = $this->extractPenulisIdSdm($item);

            if (!$idSdm) {
                $result['penulis_skipped']++;
                continue;
            }

            try {
                $model = Penulis::updateOrCreate(
                    [
                        'publikasi_id' => $publikasiId,
                        'id_sdm' => $idSdm,
                    ],
                    [
                        'id' => $item['id_penulis'],
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $result['penulis_inserted']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Penulis publikasi SISTER gagal disimpan.', [
                    'publikasi_id' => $publikasiId,
                    'id_sdm' => $idSdm,
                    'message' => $e->getMessage(),
                ]);
                $result['penulis_skipped']++;
            }
        }

        return $result;
    }

    private function requestSisterPublikasiDetail(string $baseUrl, string $token, string $publikasiId)
    {
        $client = Http::acceptJson()
            ->withToken($token)
            ->timeout(60);

        $response = $client->get($baseUrl . '/publikasi/' . $publikasiId);

        if (in_array($response->status(), [404, 405])) {
            return $client->get($baseUrl . '/publikasi:' . $publikasiId);
        }

        return $response;
    }

    private function extractSisterDetail($payload)
    {
        if (!is_array($payload)) {
            return null;
        }

        $detail = $payload['data'] ?? $payload['result'] ?? $payload['detail'] ?? $payload;

        if (isset($detail[0]) && is_array($detail[0])) {
            return $detail[0];
        }

        return is_array($detail) ? $detail : null;
    }

    private function extractSisterPenulis(array $detail)
    {
        $penulis = $detail['penulis'] ?? $detail['authors'] ?? [];

        if (!is_array($penulis)) {
            return [];
        }

        if (!isset($penulis[0])) {
            return [$penulis];
        }

        return array_values(array_filter($penulis, 'is_array'));
    }

    private function extractPenulisIdSdm(array $penulis)
    {
        $value = $penulis['id_sdm'] ??  null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function mapSisterDetailPublikasi(string $publikasiId, array $detail)
    {
        $jenisPublikasiName = $this->extractJenisPublikasiName($detail['jenis_publikasi'] ?? null);
        $jenisPublikasi = $jenisPublikasiName
            ? JenisPublikasi::whereRaw('LOWER(nama) = ?', [Str::lower($jenisPublikasiName)])->first()
            : null;
        $kategoriKegiatan = $this->resolveKategoriKegiatan($detail);

        return [
            'publikasi_id' => $publikasiId,
            'jenis_publikasi_id' => optional($jenisPublikasi)->id,
            'judul' => $detail['judul'] ?? null,
            'judul_artikel' => $detail['judul_artikel'] ?? null,
            'judul_asli' => $detail['judul_asli'] ?? null,
            'nama_jurnal' => $detail['nama_jurnal'] ?? null,
            'tanggal' => $this->parseNullableDate($detail['tanggal'] ?? $detail['tanggal_terbit'] ?? $detail['tgl_terbit'] ?? null),
            'edisi' => $detail['edisi'] ?? null,
            'volume' => is_numeric($detail['volume'] ?? null) ? $detail['volume'] : 0,
            'nomor' => is_numeric($detail['nomor'] ?? null) ? $detail['nomor'] : 0,
            'halaman' => $detail['halaman'] ?? null,
            'jumlah_halaman' => is_numeric($detail['jumlah_halaman'] ?? null) ? $detail['jumlah_halaman'] : 0,
            'penerbit' => $detail['penerbit'] ?? null,
            'seminar' => (bool) ($detail['seminar'] ?? false),
            'prosiding' => (bool) ($detail['prosiding'] ?? false),
            'nomor_paten' => $detail['nomor_paten'] ?? null,
            'pemberi_paten' => $detail['pemberi_paten'] ?? null,
            'doi' => $detail['doi'] ?? null,
            'isbn' => $detail['isbn'] ?? null,
            'issn' => $detail['issn'] ?? null,
            'e_issn' => $detail['e_issn'] ?? null,
            'tautan' => $detail['tautan'] ?? $detail['url'] ?? null,
            'keterangan' => $detail['keterangan'] ?? null,
            'id_litabmas' => $detail['id_litabmas'] ?? null,
            'id_kategori_capaian_luaran' => is_numeric($detail['id_kategori_capaian_luaran'] ?? null) ? $detail['id_kategori_capaian_luaran'] : 0,
            'quartile' => is_numeric($detail['quartile'] ?? null) ? $detail['quartile'] : 0,
            'kategori_kegiatan' => $this->stringValue($detail['kategori_kegiatan'] ?? null),
            'jenis_publikasi' => $jenisPublikasiName,
            'judul_litabmas' => $detail['judul_litabmas'] ?? null,
            'kategori_capaian_luaran' => $detail['kategori_capaian_luaran'] ?? null,
            'kategori_kegiatan_id' => optional($kategoriKegiatan)->id,
            'asal_data' => $detail['asal_data'] ?? 'SISTER',
            'bidang_keilmuan' => $this->jsonValue($detail['bidang_keilmuan'] ?? null),
        ];
    }

    private function resolveKategoriKegiatan(array $detail)
    {
        $id = $detail['kategori_kegiatan_id'] ?? $detail['id_kategori_kegiatan'] ?? null;

        if ($id && is_numeric($id)) {
            $kategori = KategoriKegiatan::find($id);
            if ($kategori) {
                return $kategori;
            }
        }

        $name = $this->stringValue($detail['kategori_kegiatan'] ?? '');
        if (!$name) {
            return null;
        }

        return KategoriKegiatan::whereRaw('LOWER(nama) = ?', [Str::lower($name)])->first();
    }

    private function mapSisterPublikasi(array $record)
    {
        $jenisPublikasiName = $this->extractJenisPublikasiName($record['jenis_publikasi'] ?? null);
        $jenisPublikasi = $jenisPublikasiName
            ? JenisPublikasi::whereRaw('LOWER(nama) = ?', [Str::lower($jenisPublikasiName)])->first()
            : null;

        if (!$jenisPublikasi) {
            Log::warning('Jenis publikasi SISTER tidak ditemukan.', [
                'jenis_publikasi' => $record['jenis_publikasi'] ?? null,
                'judul' => $record['judul'] ?? null,
            ]);

            return null;
        }

        $id = $record['id'] ?? $record['id_publikasi'] ?? $record['id_riwayat_publikasi'] ?? null;
        if (!$id) {
            Log::warning('Publikasi SISTER dilewati karena id tidak tersedia.', [
                'judul' => $record['judul'] ?? null,
            ]);

            return null;
        }

        return [
            'id' => $id,
            'id_kategori_kegiatan' => is_numeric($record['id_kategori_kegiatan'] ?? null) ? $record['id_kategori_kegiatan'] : 0,
            'a_klaim_bkd' => (bool) ($record['a_klaim_bkd'] ?? false),
            'wkt_klaim_bkd' => $this->parseDateTime($record['wkt_klaim_bkd'] ?? null),
            'judul' => $record['judul'] ?? '-',
            'quartile' => $record['quartile'] ?? null,
            'jenis_publikasi_id' => $jenisPublikasi->id,
            'tanggal' => $this->parseDate($record['tanggal'] ?? $record['tanggal_terbit'] ?? $record['tgl_terbit'] ?? null),
            'kategori_kegiatan' => $this->stringValue($record['kategori_kegiatan'] ?? ''),
            'asal_data' => $record['asal_data'] ?? 'SISTER',
            'bidang_keilmuan' => $this->jsonValue($record['bidang_keilmuan'] ?? null),
        ];
    }

    private function extractJenisPublikasiName($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            return $value['nama'] ?? $value['jenis'] ?? null;
        }

        return null;
    }

    private function parseDate($value)
    {
        if (!$value) {
            return Carbon::now()->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return Carbon::now()->toDateString();
        }
    }

    private function parseNullableDate($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDateTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function stringValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    private function jsonValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }

    private function publicationCounts(User $user)
    {
        return [
            'seminar' => Seminar::where('user_id', $user->id)->count(),
            'buku' => Buku::where('user_id', $user->id)->count(),
            'paten' => Paten::where('user_id', $user->id)->count(),
            'jurnal' => Jurnal::where('user_id', $user->id)->count(),
            'prosiding' => Prosiding::where('user_id', $user->id)->count(),
            'nonpublish' => NonPublish::where('user_id', $user->id)->count(),
            'opini' => Opini::where('user_id', $user->id)->count(),
            'karya' => Karya::where('user_id', $user->id)->count(),
            'poster' => Poster::where('user_id', $user->id)->count(),
        ];
    }

    public function GrafikJurnal(Request $request)
    {
        $tahun = $request->query('tahun', date('Y'));
        $fakultasKode = $request->query('fakultas');
        $prodiKode = $request->query('prodi');
        $jenis = $request->query('jenis');

        $years = range($tahun - 4, $tahun);
        $categories = $years;
        $series = [];

        $baseQuery = Publikasi::query()
            ->select('publikasis.id')
            ->join('penulis', 'publikasis.id', '=', 'penulis.publikasi_id')
            ->join('users', 'penulis.id_sdm', '=', 'users.id_sdm')
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->leftJoin('fakultas', 'profiles.fakultas_id', '=', 'fakultas.id')
            ->leftJoin('prodis', 'profiles.prodi_id', '=', 'prodis.id')
            ->leftJoin('jenis_publikasis', 'publikasis.jenis_publikasi_id', '=', 'jenis_publikasis.id')
            ->whereNotNull('publikasis.tanggal')
            ->whereYear('publikasis.tanggal', '>=', $tahun - 4)
            ->whereYear('publikasis.tanggal', '<=', $tahun);

        if ($fakultasKode && $fakultasKode !== 'semua') {
            $baseQuery->where('fakultas.id_unit', $fakultasKode);
        }
        if ($prodiKode && $prodiKode !== 'semua') {
            $baseQuery->where('prodis.id_unit', $prodiKode);
        }
        if ($jenis && $jenis !== 'semua') {
            $baseQuery->where('jenis_publikasis.id', $jenis);
        }

        // Automatic Drill-down Logic
        if (!$fakultasKode || $fakultasKode === 'semua') {
            // Show breakdown by Fakultas
            $groups = Fakultas::all();
            foreach ($groups as $group) {
                $counts = [];
                foreach ($years as $y) {
                    $counts[] = (clone $baseQuery)->where('fakultas.id', $group->id)->whereYear('publikasis.tanggal', $y)->distinct('publikasis.id')->count('publikasis.id');
                }
                $series[] = ['name' => $group->nama, 'data' => $counts];
            }
            $stateLabel = 'fakultas';
        } elseif (!$prodiKode || $prodiKode === 'semua') {
            // Show breakdown by Prodi
            $groups = Prodi::whereHas('fakultas', function ($q) use ($fakultasKode) {
                $q->where('id_unit', $fakultasKode);
            })->get();
            foreach ($groups as $group) {
                $counts = [];
                foreach ($years as $y) {
                    $counts[] = (clone $baseQuery)->where('prodis.id', $group->id)->whereYear('publikasis.tanggal', $y)->distinct('publikasis.id')->count('publikasis.id');
                }
                $series[] = ['name' => $group->nama, 'data' => $counts];
            }
            $stateLabel = 'prodi';
        } else {
            // Show breakdown by Jenis Publikasi
            $keywords = ['Jurnal', 'Prosiding'];
            $groups = JenisPublikasi::where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('nama', 'like', $keyword . '%');
                }
            })
                ->orderBy('nama')
                ->get();
            foreach ($groups as $group) {
                $counts = [];
                foreach ($years as $y) {
                    $counts[] = (clone $baseQuery)->where('jenis_publikasis.id', $group->id)->whereYear('publikasis.tanggal', $y)->distinct('publikasis.id')->count('publikasis.id');
                }
                $series[] = ['name' => $group->nama, 'data' => $counts];
            }
            $stateLabel = 'jenis';
        }

        return response()->json([
            'categories' => $categories,
            'series' => $series,
            'state' => $stateLabel
        ], 200);
    }
    public function publikasiAll($state)
    {
        $jenisPublikasi = JenisPublikasi::all()->first(function ($item) use ($state) {
            return Str::slug($item->nama, '_') === $state;
        });
        $data = [];

        if ($jenisPublikasi) {
            $data = Publikasi::with([
                'detailPublikasi:publikasi_id,nama_jurnal,tautan',
                'penulis.user:name,id_sdm'
            ])
                ->where('jenis_publikasi_id', $jenisPublikasi->id)
                ->orderByDesc('tanggal')
                ->select(
                    'id',
                    'judul',
                    'tanggal',
                    'jenis_publikasi_id',
                    'quartile',
                    'asal_data'
                )
                ->get()
                ->map(function ($item) {
                    $item->tahun = $item->tanggal
                        ? Carbon::parse($item->tanggal)->year
                        : null;

                    return $item;
                });
        }

        return response()->json($data, 200);
    }
    public function all()
    {
        $jenis = Publikasi::query()
            ->join('jenis_publikasis', 'jenis_publikasis.id', '=', 'publikasis.jenis_publikasi_id')
            ->where(function ($query) {
                $query->where('jenis_publikasis.nama', 'like', 'Jurnal%')
                    ->orWhere('jenis_publikasis.nama', 'like', 'Prosiding%');
            })
            ->select(
                'jenis_publikasis.id',
                'jenis_publikasis.nama',
                DB::raw('count(publikasis.id) as total')
            )
            ->groupBy('jenis_publikasis.id', 'jenis_publikasis.nama')
            ->orderBy('jenis_publikasis.nama')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'key' => Str::slug($item->nama, '_'),
                    'total' => (int) $item->total,
                ];
            });
        $legacy = [];
        foreach ($jenis as $item) {
            $legacy[$item['key']] = $item['total'];
        }

        return response()->json(array_merge($legacy, [
            'jenis' => $jenis,
            'total' => $jenis->sum('total'),
        ]));
    }



    public function public()
    {
        $akun = Profile::count();
        $scopus = Profile::whereNotNull('scopus')->where('scopus', '!=', '')->count();
        $thomson = Profile::whereNotNull('thomson')->where('thomson', '!=', '')->count();
        $google = Profile::whereNotNull('google')->where('google', '!=', '')->count();
        $sinta = Profile::whereNotNull('sinta')->where('sinta', '!=', '')->count();
        return response()->json(['akun' => $akun, 'scopus' => $scopus, 'thomson' => $thomson, 'sinta' => $sinta, 'google' => $google]);
    }

    public function headerStats()
    {
        $akun = Profile::count();

        $sinta = Publikasi::whereRaw('UPPER(asal_data) = ?', ['SINTA'])->count();
        $google = Publikasi::whereRaw('UPPER(asal_data) = ?', ['GOOGLE SCHOLAR'])->count();
        $thomson = Publikasi::whereRaw('UPPER(asal_data) = ?', ['THOMSON'])->count();
        $scopus = Publikasi::whereRaw('UPPER(asal_data) = ?', ['SCOPUS'])->count();

        return response()->json([
            'akun' => $akun,
            'scopus' => $scopus,
            'thomson' => $thomson,
            'google' => $google,
            'sinta' => $sinta
        ]);
    }

    public function repo()
    {
        $users = \App\Models\User::has('profile')->with('profile.fakultas')->get();

        $data = $users->map(function ($user) {
            return [
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->name,
                    'profile' => $user->profile
                ],
                'gelar_depan' => '',
                'gelar_belakang' => '',
                'fakultas' => $user->profile && $user->profile->fakultas ? $user->profile->fakultas->nama : null
            ];
        });

        return response()->json($data, 200);
    }
    public function cari($search)
    {
        $seminar = Seminar::with(['fileReview', 'fileTurnitin', 'fileKoresponden'])->where('nama', 'like', '%' . $search . '%')->orWhere('judul', 'like', '%' . $search . '%')->select('seminars.*')->addSelect(DB::raw("'seminar' as tipe"))->get();
        $jurnal = Jurnal::with(['fileReview', 'fileTurnitin', 'fileKoresponden'])->where('nama', 'like', '%' . $search . '%')->orWhere('judul', 'like', '%' . $search . '%')->select('jurnals.*')->addSelect(DB::raw("'jurnal' as tipe"))->get();
        $prosiding = Prosiding::with(['fileReview', 'fileTurnitin', 'fileKoresponden'])->where('nama', 'like', '%' . $search . '%')->orWhere('judul', 'like', '%' . $search . '%')->select('prosidings.*')->addSelect(DB::raw("'prosiding' as tipe"))->get();
        $data = $seminar->concat($jurnal);
        $data = $data->concat($prosiding);
        $sorted = $data->sortBy('nama')->values();
        return response()->json($sorted, 200);
    }
    public function user($nama)
    {
        $data = $this->resolveUserByAlias($nama);

        if (!$data) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        return response()->json($data, 200);
    }

    public function publikasiByAlias(Request $request, $alias)
    {
        $configuredKey = config('services.publication_api.key');
        $requestKey = $request->header('X-API-KEY') ?: $request->bearerToken();

        if (!$configuredKey) {
            return response()->json([
                'message' => 'PUBLICATION_API_KEY belum dikonfigurasi.',
            ], 500);
        }

        if (!$requestKey || !hash_equals((string) $configuredKey, (string) $requestKey)) {
            return response()->json([
                'message' => 'API key tidak valid.',
            ], 401);
        }
        $user = $this->resolveUserByAlias($alias);

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
                'data' => [],
            ], 404);
        }

        if (!$user->id_sdm) {
            return response()->json([
                'message' => 'id_sdm user belum tersedia.',
                'data' => [],
            ], 200);
        }

        $search = $request->query('search');
        $type = $request->query('jenis');
        $year = $request->query('tahun');
        $limit = $request->query('limit', 10);

        $query = Publikasi::with([
            'jenisPublikasi' => function ($q) {
                $q->where(function ($q2) {
                    $q2->where('nama', 'like', 'Jurnal%')
                        ->orWhere('nama', 'like', 'Prosiding%');
                });
            },
            'detailPublikasi:publikasi_id,nama_jurnal,tautan',
            'penulis.user'
        ])
            ->whereHas('penulis', function ($q) use ($user) {
                $q->where('id_sdm', $user->id_sdm);
            });

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('LOWER(judul)'), 'like', '%' . strtolower($search) . '%')
                    ->orWhere('asal_data', 'like', '%' . $search . '%')
                    ->orWhereHas('detailPublikasi', function ($q2) use ($search) {
                        $q2->where(DB::raw('LOWER(nama_jurnal)'), 'like', '%' . strtolower($search) . '%');
                    });
            });
        }

        if (!empty($type) && $type !== 'semua' && $type !== 'all') {
            $query->whereHas('jenisPublikasi', function ($q) use ($type) {
                $q->where('nama', $type);
            });
        }

        if (!empty($year) && $year !== 'semua' && $year !== 'all') {
            $query->whereYear('tanggal', $year);
        }

        $data = $query->select([
            'id',
            'judul',
            'tanggal',
            'jenis_publikasi_id',
            'quartile',
            'asal_data',
        ])
            ->orderByDesc('tanggal')
            ->paginate($limit);

        $data->through(function ($item) {
            $item->tahun = $item->tanggal ? Carbon::parse($item->tanggal)->year : null;
            $item->tipe = optional($item->jenisPublikasi)->nama;
            return $item;
        });

        return response()->json([
            'message' => 'Data publikasi berhasil diambil.',
            'data' => $data,
        ], 200);
    }

    private function resolveUserByAlias($value)
    {
        $keyword = str_replace('_', ' ', $value);

        return User::with(['profile.fakultas', 'pendidikan'])
            ->whereRaw('LOWER(alias) = ?', [Str::lower($value)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($keyword)])
            ->first();
    }

    public function publikasi($nama, $publikasi)
    {
        $user = $this->resolveUserByAlias($nama);

        if (!$user) {
            return response()->json([], 200);
        }

        if ($publikasi == 'Jurnal') {
            $data = Jurnal::where(['user_id' => $user->id])->select('jurnals.*')->addSelect(DB::raw("'jurnal' as tipe"))->orderBy('tahun', 'DESC')->get();
        } else if ($publikasi == 'Prosiding') {
            $data = Prosiding::where(['user_id' => $user->id])->select('prosidings.*')->addSelect(DB::raw("'prosiding' as tipe"))->orderBy('tahun', 'DESC')->get();
        } else if ($publikasi == 'Workshop') {
            $data = Workshop::where(['user_id' => $user->id])->select(DB::raw('YEAR(tanggal) as tahun,workshops.*'))->addSelect(DB::raw("'workshop' as tipe"))->orderBy('tanggal', 'DESC')->get();
        } else if ($publikasi == 'Buku') {
            $data = Buku::where(['user_id' => $user->id])->select('bukus.*')->addSelect(DB::raw("'buku' as tipe"))->orderBy('tahun', 'DESC')->get();
        } else if ($publikasi == 'Award') {
            $data = Award::where(['user_id' => $user->id])->select(DB::raw('YEAR(tanggal) as tahun,awards.*'))->addSelect(DB::raw("'award' as tipe"))->orderBy('tanggal', 'DESC')->get();
        } else if ($publikasi == 'Karya') {
            $data = Karya::where(['user_id' => $user->id])->select(DB::raw('karyas.*'))->addSelect(DB::raw("'karya' as tipe"))->orderBy('tahun', 'DESC')->get();
        }
        return response()->json($data, 200);
    }
    public function publikasiASId($id)
    {
        $user = User::find($id);
        $data['jurnal'] = Jurnal::where(['user_id' => $user->id])->select('jurnals.*')->addSelect(DB::raw("'jurnal' as tipe"))->orderBy('tahun', 'DESC')->get();
        $data['prosiding'] = Prosiding::where(['user_id' => $user->id])->select('prosidings.*')->addSelect(DB::raw("'prosiding' as tipe"))->orderBy('tahun', 'DESC')->get();
        $data['workshop'] = Workshop::where(['user_id' => $user->id])->select(DB::raw('YEAR(tanggal) as tahun,workshops.*'))->addSelect(DB::raw("'workshop' as tipe"))->orderBy('tanggal', 'DESC')->get();
        $data['buku'] = Buku::where(['user_id' => $user->id])->select('bukus.*')->addSelect(DB::raw("'buku' as tipe"))->orderBy('tahun', 'DESC')->get();
        $data['award'] = Award::where(['user_id' => $user->id])->select(DB::raw('YEAR(tanggal) as tahun,awards.*'))->addSelect(DB::raw("'award' as tipe"))->orderBy('tanggal', 'DESC')->get();
        return response()->json($data, 200);
    }
}
