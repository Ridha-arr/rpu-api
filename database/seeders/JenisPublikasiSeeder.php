<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisPublikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('jenis_publikasis')->insert([
            [
                'id' => 11,
                'nama' => 'Monograf',
            ],
            [
                'id' => 12,
                'nama' => 'Buku referensi',
            ],
            [
                'id' => 13,
                'nama' => 'Buku lainnya',
            ],
            [
                'id' => 14,
                'nama' => 'Book chapter nasional',
            ],
            [
                'id' => 15,
                'nama' => 'Book chapter internasional',
            ],
            [
                'id' => 21,
                'nama' => 'Jurnal nasional',
            ],
            [
                'id' => 22,
                'nama' => 'Jurnal nasional terakreditasi',
            ],
            [
                'id' => 23,
                'nama' => 'Jurnal internasional',
            ],
            [
                'id' => 24,
                'nama' => 'Jurnal internasional bereputasi',
            ],
            [
                'id' => 25,
                'nama' => 'Artikel ilmiah',
            ],
            [
                'id' => 26,
                'nama' => 'Makalah ilmiah',
            ],
            [
                'id' => 27,
                'nama' => 'Tulisan ilmiah',
            ],
            [
                'id' => 28,
                'nama' => 'Abstrak buku/pustaka',
            ],
            [
                'id' => 29,
                'nama' => 'Penemuan teknologi',
            ],
            [
                'id' => 31,
                'nama' => 'Prosiding seminar nasional',
            ],
            [
                'id' => 32,
                'nama' => 'Prosiding seminar internasional',
            ],
            [
                'id' => 33,
                'nama' => 'Poster seminar nasional',
            ],
            [
                'id' => 34,
                'nama' => 'Poster seminar internasional',
            ],
            [
                'id' => 41,
                'nama' => 'Paten nasional',
            ],
            [
                'id' => 42,
                'nama' => 'Paten internasional',
            ],
            [
                'id' => 43,
                'nama' => 'Hak cipta nasional',
            ],
            [
                'id' => 44,
                'nama' => 'Hak cipta internasional',
            ],
            [
                'id' => 51,
                'nama' => 'Rancangan dan karya seni monumental',
            ],
            [
                'id' => 52,
                'nama' => 'Rancangan dan karya seni rupa',
            ],
            [
                'id' => 53,
                'nama' => 'Rancangan dan karya seni kriya',
            ],
            [
                'id' => 54,
                'nama' => 'Rancangan dan karya seni pertunjukan',
            ],
            [
                'id' => 55,
                'nama' => 'Karya desain',
            ],
            [
                'id' => 56,
                'nama' => 'Karya sastra',
            ],
            [
                'id' => 61,
                'nama' => 'Koran/majalah populer/majalah umum',
            ],
            [
                'id' => 71,
                'nama' => 'Hasil penelitian/pemikiran yang tidak dipublikasikan',
            ],
            [
                'id' => 72,
                'nama' => 'Hasil kerjasama industri yang tidak dipublikasikan',
            ],
            [
                'id' => 9999,
                'nama' => 'Lain-lain',
            ],
        ]);
    }
}
