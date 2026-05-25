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

        $profile = Profile::where('user_id', auth()->user()->id)->update([
            'about' => $request->about,
            'fokus' => $request->fokus,
            'scopus' => $request->scopus,
            'thomson' => $request->thomson,
            'google' => $request->google,
            'sinta' => $request->sinta,
        ]);

        if ($request->has('alias')) {
            User::where('id', auth()->user()->id)->update([
                'alias' => $request->alias
            ]);
        }

        return response()->json($profile, 200);
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
