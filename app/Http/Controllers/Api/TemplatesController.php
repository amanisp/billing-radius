<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplatesController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        $id = Auth::id();

        if ($id) {
            return User::find($id);
        }

        return null;
    }

    protected $defaultTemplates =  [
        'invoice_terbit' => "Yth. Bapak/Ibu [full_name],\n\nKami informasikan bahwa invoice Anda telah diterbitkan dan dapat segera dilakukan pembayaran. Berikut rincian tagihan Anda:\n\n━━━━━━━━━━━━━━━━━━━━\nID Pelanggan: [uid]\nJumlah: Rp [amount]\nDiskon: Rp [discount]\nTotal Tagihan: Rp [total]\nLayanan: Internet [pppoe_user] - [pppoe_profile]\nPeriode:\n[period]\nJatuh Tempo: [due_date]\n━━━━━━━━━━━━━━━━━━━━\n\nPembayaran bisa melalui tautan berikut:\n[payment_url]\n\nMohon agar pembayaran dilakukan sebelum tanggal jatuh tempo.\n\nTerima kasih atas kepercayaan Anda menggunakan layanan kami.\n\n[footer]\n\n_Ini adalah pesan otomatis, mohon tidak membalas pesan ini._",

        'payment_paid' => "Yth. Bapak/Ibu [full_name],\n\nKami menginformasikan bahwa pembayaran Anda untuk invoice #[no_invoice] telah *berhasil* kami terima dengan rincian sebagai berikut:\n\nJumlah Pembayaran: Rp [total]\nLayanan: [pppoe_user] - [pppoe_profile]\nPeriode: [period]\nMetode Pembayaran: [payment_gateway]\n\nTerima kasih atas kepercayaan Anda menggunakan layanan kami.\n\nHormat kami,\n[footer]\n\n_Ini adalah pesan otomatis, mohon tidak membalas pesan ini._",

        'payment_cancel' => "Yth. Bapak/Ibu [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah dibatalkan.\n\nRincian Tagihan:\nJumlah: [total]\nTanggal Invoice: [invoice_date]\nJatuh Tempo: [due_date]\nPeriode: [period]\n\nMohon segera melakukan pembayaran untuk menghindari gangguan layanan.\n\n_Pesan ini dikirim secara otomatis. Mohon tidak membalas langsung ke pesan ini._",

        'account_suspend' => "Yth. Pelanggan [full_name],\n\nLayanan internet Anda sementara ditangguhkan karena pembayaran invoice belum diterima.\n\nUntuk informasi lebih lanjut atau bantuan, silakan hubungi layanan pelanggan kami.\n\n[footer]",

        'account_active' => "Yth. Pelanggan [full_name],\n\nLayanan internet Anda telah berhasil diaktifkan.\n\nUsername: [pppoe_user]\nProfil: [pppoe_profile]\n\nSelamat menikmati layanan kami!\n\n_Pesan ini dikirim secara otomatis. Mohon tidak membalas langsung ke pesan ini_",

        'invoice_reminder' => "Halo [full_name],\n\nIni adalah pengingat untuk pembayaran Anda yang akan datang.\nID Pelanggan: [uid]\nNomor Invoice: #[no_invoice]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSilakan lakukan pembayaran sebelum jatuh tempo.\n\n[payment_gateway]\n\n[footer]",

        'invoice_overdue' => "Halo [full_name],\n\nInvoice Anda #[no_invoice] telah melewati jatuh tempo.\nID Pelanggan: [uid]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSegera lakukan pembayaran untuk menghindari suspend layanan.\n\n[payment_gateway]\n\n[footer]",
    ];

    public function index()
    {
        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            // cek apakah template group sudah ada
            $hasTemplates = WhatsappTemplates::where(
                'group_id',
                $user->group_id
            )->exists();

            // jika belum ada → auto initialize
            if (!$hasTemplates) {

                foreach ($this->defaultTemplates as $type => $content) {

                    WhatsappTemplates::create([
                        'group_id' => $user->group_id,
                        'template_type' => $type,
                        'content' => $content,
                    ]);
                }
            }

            $templates = WhatsappTemplates::where(
                'group_id',
                $user->group_id
            )
                ->orderBy('id')
                ->get();

            return ResponseFormatter::success(
                $templates,
                'Template berhasil dimuat',
                200
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    public function show($type)
    {
        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $template = WhatsappTemplates::where('template_type', $type)
                ->where(function ($query) use ($user) {
                    $query->where('group_id', $user->group_id)
                        ->orWhereNull('group_id');
                })
                ->first();

            if (!$template) {
                return ResponseFormatter::error(
                    null,
                    'Template tidak ditemukan',
                    404
                );
            }

            return ResponseFormatter::success(
                $template,
                'Detail template berhasil dimuat',
                200
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    public function update(Request $request, $type)
    {
        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'content' => 'required|string'
            ]);

            $template = WhatsappTemplates::where(
                'template_type',
                $type
            )
                ->where('group_id', $user->group_id)
                ->first();

            // jika belum ada template group → clone dari default
            if (!$template) {

                $defaultTemplate = WhatsappTemplates::where(
                    'template_type',
                    $type
                )
                    ->whereNull('group_id')
                    ->first();

                $template = WhatsappTemplates::create([
                    'group_id' => $user->group_id,
                    'template_type' => $type,
                    'content' => $defaultTemplate?->content ?? '',
                ]);
            }

            $template->update([
                'content' => $request->content
            ]);

            return ResponseFormatter::success(
                $template,
                'Template berhasil diperbarui',
                200
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    public function reset($type)
    {
        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            if (!isset($this->defaultTemplates[$type])) {

                return ResponseFormatter::error(
                    null,
                    'Template default tidak ditemukan',
                    404
                );
            }

            $template = WhatsappTemplates::where(
                'template_type',
                $type
            )
                ->where('group_id', $user->group_id)
                ->first();

            if (!$template) {

                $template = WhatsappTemplates::create([
                    'group_id' => $user->group_id,
                    'template_type' => $type,
                    'content' => $this->defaultTemplates[$type],
                ]);
            } else {

                $template->update([
                    'content' => $this->defaultTemplates[$type]
                ]);
            }

            return ResponseFormatter::success(
                $template,
                'Template berhasil direset',
                200
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }
}
