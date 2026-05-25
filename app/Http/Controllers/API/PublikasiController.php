<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class PublikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {}

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
        if ($request->img) {

            $fileName = $request->img->store('public/images/' . $request->user_id);
            $fileName = str_replace("public/", "storage/", $fileName);
            Publikasi::create([
                'user_id' => $request->user_id,
                'img' => $fileName,
                'link' => $request->link
            ]);
        }
        return response()->json([
            'message' => 'Berhasil Diupload'
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Penulis::with(['publikasi'])->where('user_id', $id)->get();
        return response()->json($data, 200);
    }

    public function count()
    {
        $data = Penulis::where('id_sdm', auth()->user()->id_sdm)->count();
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
        $publikasi = Publikasi::find($id);
        if ($request->img) {
            if (File::exists($publikasi->img)) File::delete($publikasi->img);
            $fileName = $request->img->store('public/images/' . $request->user_id);
            $fileName = str_replace("public/", "storage/", $fileName);
            $publikasi->img = $fileName;
        }
        $publikasi->link = $request->link;
        $publikasi->save();
        return response()->json([
            'message' => 'Berhasil Diupload'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $publikasi = Publikasi::find($id);
        if (File::exists($publikasi->img)) File::delete($publikasi->img);
        $publikasi->delete();
        return response()->json([
            'message' => 'Berhasil Dihapus'
        ], 200);
    }
}
