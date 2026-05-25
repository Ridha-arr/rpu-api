<?php

namespace App\Http\Controllers;

use App\Models\MigrateUsers;
use App\Models\User;
use Illuminate\Http\Request;

class MigrateController extends Controller
{
    public function index()
    {
        $data = MigrateUsers::where('level',3)->get();
        foreach ($data as $item) {
            User::create([
                'nip'=>$item->nip,
                'name' => $item->nama,
                'email'=>$item->email,
                'password'=>$item->password
            ]);
        }
        return 'end';
    }
}
