<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Publikasi;
use Illuminate\Http\Request;

class PublicationSyncController extends Controller
{
    public function index(Request $request)
    {
        $key = config('services.publication_sync.key');
        if (!is_string($key) || $key === '') {
            return response()->json(['message' => 'Integrasi belum dikonfigurasi.'], 503);
        }
        $provided = $request->bearerToken();
        if (!is_string($provided) || !hash_equals($key, $provided)) {
            return response()->json(['message' => 'Tidak diizinkan.'], 401);
        }

        $data = $request->validate([
            'id_sdm' => 'required|array|min:1|max:200',
            'id_sdm.*' => 'required|uuid|distinct',
            'page' => 'sometimes|integer|min:1',
        ]);

        $page = $data['page'] ?? 1;
        $result = Publikasi::query()
            ->with(['jenisPublikasi:id,nama', 'detailPublikasi', 'penulis.user:id_sdm,name'])
            ->whereHas('penulis', fn($query) => $query->whereIn('id_sdm', $data['id_sdm']))
            ->orderBy('id')
            ->paginate(100, ['id', 'judul', 'tanggal', 'quartile', 'jenis_publikasi_id'], 'page', $page);

        return response()->json([
            'data' => $result->getCollection()->map(function ($item) {
                $detail = $item->detailPublikasi;
                return [
                    'id' => $item->id,
                    'judul' => $item->judul,
                    'tanggal' => $item->tanggal,
                    'quartile' => $item->quartile,
                    'jenis_publikasi' => optional($item->jenisPublikasi)->nama,
                    'asal_data' => $item->asal_data,
                    'detail' => $detail ? [
                        'nama_jurnal' => $detail->nama_jurnal,
                        'penerbit' => $detail->penerbit,
                        'tautan' => $detail->tautan,
                        'doi' => $detail->doi,
                        'volume' => $detail->volume,
                        'nomor' => $detail->nomor,
                        'sinta_akred' => $detail->sinta_akred,
                    ] : null,
                    'penulis' => $item->penulis->map(fn($author) => [
                        'id' => $author->id,
                        'id_sdm' => $author->id_sdm,
                        'nama' => optional($author->user)->name,
                        'urutan' => $author->urutan,
                        'peran' => $author->peran,
                        'corresponding_author' => (bool) $author->corresponding_author,
                    ])->values(),
                ];
            })->values(),
            'current_page' => $result->currentPage(),
            'last_page' => $result->lastPage(),
            'total' => $result->total(),
        ]);
    }
}
