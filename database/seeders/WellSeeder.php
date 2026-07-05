<?php

namespace Database\Seeders;

use App\Models\Well;
use Illuminate\Database\Seeder;

class WellSeeder extends Seeder
{
    public function run(): void
    {
        $wells = [
            ['code'=>'A-001','village'=>'Guidan Roumdji','region'=>'Maradi','department'=>'Guidan Roumdji','commune'=>'Guidan Roumdji','status'=>'operational'],
            ['code'=>'A-002','village'=>'Dakoro','region'=>'Maradi','department'=>'Dakoro','commune'=>'Dakoro','status'=>'operational'],
            ['code'=>'A-003','village'=>'Tessaoua','region'=>'Maradi','department'=>'Tessaoua','commune'=>'Tessaoua','status'=>'not_working'],
            ['code'=>'A-004','village'=>'Aguié','region'=>'Maradi','department'=>'Aguié','commune'=>'Aguié','status'=>'operational'],
            ['code'=>'A-005','village'=>'Madarounfa','region'=>'Maradi','department'=>'Madarounfa','commune'=>'Madarounfa','status'=>'suspended'],
            ['code'=>'A-006','village'=>'Mayahi','region'=>'Maradi','department'=>'Mayahi','commune'=>'Mayahi','status'=>'operational'],
            ['code'=>'A-007','village'=>'Gazaoua','region'=>'Maradi','department'=>'Guidan Roumdji','commune'=>'Gazaoua','status'=>'operational'],
            ['code'=>'A-008','village'=>'Sabon Machi','region'=>'Maradi','department'=>'Tessaoua','commune'=>'Sabon Machi','status'=>'not_working'],
            ['code'=>'A-009','village'=>'Dan Issa','region'=>'Maradi','department'=>'Aguié','commune'=>'Dan Issa','status'=>'operational'],
            ['code'=>'A-010','village'=>'Gabi','region'=>'Maradi','department'=>'Dakoro','commune'=>'Gabi','status'=>'operational'],
            ['code'=>'B-001','village'=>'Mirriah','region'=>'Zinder','department'=>'Mirriah','commune'=>'Mirriah','status'=>'operational'],
            ['code'=>'B-002','village'=>'Magaria','region'=>'Zinder','department'=>'Magaria','commune'=>'Magaria','status'=>'not_working'],
            ['code'=>'B-003','village'=>'Matameye','region'=>'Zinder','department'=>'Matameye','commune'=>'Matameye','status'=>'operational'],
            ['code'=>'B-004','village'=>'Gouré','region'=>'Zinder','department'=>'Gouré','commune'=>'Gouré','status'=>'suspended'],
            ['code'=>'B-005','village'=>'Tanout','region'=>'Zinder','department'=>'Tanout','commune'=>'Tanout','status'=>'operational'],
            ['code'=>'B-006','village'=>'Dungass','region'=>'Zinder','department'=>'Magaria','commune'=>'Dungass','status'=>'operational'],
            ['code'=>'B-007','village'=>'Kantché','region'=>'Zinder','department'=>'Matameye','commune'=>'Kantché','status'=>'not_working'],
            ['code'=>'B-008','village'=>'Guidiguir','region'=>'Zinder','department'=>'Gouré','commune'=>'Guidiguir','status'=>'operational'],
            ['code'=>'B-009','village'=>'Kellé','region'=>'Zinder','department'=>'Mirriah','commune'=>'Kellé','status'=>'operational'],
            ['code'=>'B-010','village'=>'Droum','region'=>'Zinder','department'=>'Tanout','commune'=>'Droum','status'=>'operational'],
        ];

        foreach ($wells as $well) {
            Well::create($well);
        }
    }
}