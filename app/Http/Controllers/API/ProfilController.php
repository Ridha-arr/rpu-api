<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ProfilController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $data = User::with(['profile.prodi.fakultas', 'biodata', 'publikasi' => function ($query) {
            $query->orderBy('tanggal', 'desc');
        }])->get();
        return response()->json([
            'user' => $data,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'alias' => 'nullable|string|unique:users,alias,' . auth()->user()->id,
        ], [
            'alias.unique' => 'Alias ini sudah digunakan oleh pengguna lain. Silakan pilih alias yang berbeda.',
        ]);

        $existingProfile = Profile::where('user_id', auth()->user()->id)->first();
        $sintaOverall = null;
        $sinta3yrs = null;
        $shouldScrape = false;

        if ($request->filled('sinta')) {
            if (!$existingProfile || $existingProfile->sinta !== $request->sinta || empty($existingProfile->sinta_overall)) {
                $shouldScrape = true;
            } else {
                $sintaOverall = $existingProfile->sinta_overall;
                $sinta3yrs = $existingProfile->sinta_3yr;
            }
        }

        if ($shouldScrape) {
            try {
                $sintaId = trim($request->sinta);
                $url = "https://sinta.kemdiktisaintek.go.id/authors/profile/" . $sintaId;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $html = curl_exec($ch);
                curl_close($ch);

                if ($html !== false) {
                    $pattern_overall = '/<div class="pr-num">([^<]+)<\/div>\s*<div class="pr-txt">\s*SINTA Score Overall\s*<\/div>/is';
                    $pattern_3yrs = '/<div class="pr-num">([^<]+)<\/div>\s*<div class="pr-txt">\s*SINTA Score 3Yr\s*<\/div>/is';

                    if (preg_match($pattern_overall, $html, $matches_overall)) {
                        $sintaOverall = trim($matches_overall[1]);
                    }

                    if (preg_match($pattern_3yrs, $html, $matches_3yrs)) {
                        $sinta3yrs = trim($matches_3yrs[1]);
                    }
                }
            } catch (\Exception $e) {
                // Log error or ignore to prevent blocking
            }
        }

        $updateData = [
            'about' => $request->about,
            'fokus' => $request->fokus,
            'scopus' => $request->scopus,
            'thomson' => $request->thomson,
            'google' => $request->google,
            'sinta' => $request->sinta,
        ];

        if (empty($request->sinta)) {
            $updateData['sinta_overall'] = null;
            $updateData['sinta_3yr'] = null;
        } elseif ($sintaOverall !== null) {
            $updateData['sinta_overall'] = $sintaOverall;
            $updateData['sinta_3yr'] = $sinta3yrs;
        }

        $profile = Profile::where('user_id', auth()->user()->id)->update($updateData);

        if ($request->has('alias')) {
            User::where('id', auth()->user()->id)->update([
                'alias' => $request->alias
            ]);
        }

        return response()->json($profile, 200);
    }

    public function syncSinta()
    {
        $profile = Profile::where('user_id', auth()->user()->id)->first();
        if (!$profile || empty($profile->sinta)) {
            return response()->json(['message' => 'SINTA ID belum diisi pada profil Anda.'], 422);
        }

        $sintaId = trim($profile->sinta);
        $url = "https://sinta.kemdiktisaintek.go.id/authors/profile/" . $sintaId;

        $sintaOverall = null;
        $sinta3yrs = null;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $html = curl_exec($ch);
            curl_close($ch);

            if ($html !== false) {
                $pattern_overall = '/<div class="pr-num">([^<]+)<\/div>\s*<div class="pr-txt">\s*SINTA Score Overall\s*<\/div>/is';
                $pattern_3yrs = '/<div class="pr-num">([^<]+)<\/div>\s*<div class="pr-txt">\s*SINTA Score 3Yr\s*<\/div>/is';
                if (preg_match($pattern_overall, $html, $matches_overall)) {
                    $sintaOverall = trim($matches_overall[1]);
                }
                if (preg_match($pattern_3yrs, $html, $matches_3yrs)) {
                    $sinta3yrs = trim($matches_3yrs[1]);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal terhubung ke portal SINTA.'], 500);
        }

        if ($sintaOverall === null) {
            return response()->json(['message' => 'Gagal mengambil data dari portal SINTA. Pastikan SINTA ID Anda valid.'], 422);
        }

        $profile->update([
            'sinta_overall' => $sintaOverall,
            'sinta_3yr' => $sinta3yrs,
        ]);

        return response()->json([
            'message' => 'Sinkronisasi skor SINTA berhasil.',
            'profile' => $profile
        ], 200);
    }

    public function storeProfile(Request $request)
    {

        $profile = Profile::updateOrCreate(['user_id' => auth()->user()->id], []);
        return response()->json($request, 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show()
    {
        $data = User::with(['profile', 'biodata'])->where('id', auth()->user()->id)->first();
        return response()->json($data, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
