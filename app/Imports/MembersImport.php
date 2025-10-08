<?php

namespace App\Imports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MembersImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        ini_set('max_execution_time', 300); // 5 menit
        ini_set('memory_limit', '512M');

        $errors = [];

        foreach ($rows as $index => $row) {
            // Lewati 5 baris pertama karena header
            if ($index < 6) continue;

            // Pastikan kolom 'name' memiliki data, jika kosong skip baris ini
            if (!isset($row[0]) || empty(trim($row[0]))) continue;

            $rawPhone = $row[1] ?? null;
            $phone_number = null;
            if ($rawPhone) {
                $rawPhone = preg_replace('/[^0-9]/', '', $rawPhone); // Hilangkan karakter non-digit
                if (str_starts_with($rawPhone, '0')) {
                    $phone_number = '62' . substr($rawPhone, 1); // 08xx → 628xx
                } elseif (str_starts_with($rawPhone, '8')) {
                    $phone_number = '62' . $rawPhone; // 8xx → 628xx
                } else {
                    $phone_number = $rawPhone; // Biarkan jika sudah 62xxx atau lainnya
                }
            }

            // Data yang akan divalidasi
            $data = [
                'name' => trim($row[0]),
                'phone_number' => $phone_number,
                'email' => $row[2] ?? null,
                'nik' => $row[3] ?? null,
                'address' => $row[4] ?? null,
            ];

            // Validasi data sebelum disimpan
            $validator = Validator::make($data, [
                'name' => [
                    'required',
                    Rule::unique('members')->where(fn($query) =>
                    $query->where('group_id', Auth::user()->group_id))
                ],
                'phone_number' => ['nullable', 'string', 'min:9'],
                'email' => 'nullable|email',
                'nik' => 'nullable|string|digits:16',
                'address' => 'nullable|string',
            ]);

            // Jika validasi gagal, tambahkan ke daftar error
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = [
                            'row' => $index + 1, // Baris asli di Excel
                            'column' => $field, // Nama kolom yang error
                            'value' => $data[$field] ?? null, // Isi kolom yang error
                            'error' => $message // Pesan error spesifik
                        ];
                    }
                }
                continue; // Skip penyimpanan jika ada error
            }

            // Simpan ke database jika validasi sukses
            Member::create(array_merge($data, ['group_id' => Auth::user()->group_id]));
        }

        // Jika ada error, lemparkan ke exception agar bisa dikirim ke AJAX
        if (!empty($errors)) {
            throw new \Exception(json_encode($errors));
        }
    }
}
