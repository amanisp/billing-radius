<?php
return [
    'invoice_terbit' => "Yth. Bapak/Ibu [full_name],\n\nKami informasikan bahwa invoice Anda telah diterbitkan dan dapat segera dilakukan pembayaran. Berikut rincian tagihan Anda:\n\n━━━━━━━━━━━━━━━━━━━━\nID Pelanggan: [uid]\nJumlah: Rp [amount]\nDiskon: [discount]\nTotal Tagihan: Rp [total]\nLayanan: Internet [pppoe_user] - [pppoe_profile]\nPeriode: [period]\nJatuh Tempo: [due_date]\n━━━━━━━━━━━━━━━━━━━━\n\nPembayaran bisa melalui tautan berikut:\n[payment_url]\n\nMohon agar pembayaran dilakukan sebelum tanggal jatuh tempo.\n\nTerima kasih atas kepercayaan Anda menggunakan layanan kami.\n\n[footer]\n\n_Ini adalah pesan otomatis, mohon tidak membalas pesan ini._",

    'payment_paid' => "Yth. Bapak/Ibu [full_name],\n\nKami menginformasikan bahwa pembayaran Anda untuk invoice #[no_invoice] telah *berhasil* kami terima dengan rincian sebagai berikut:\n\nJumlah Pembayaran: Rp [total]\nLayanan: [pppoe_user] - [pppoe_profile]\nPeriode: [period]\nMetode Pembayaran: [payment_gateway]\n\nTerima kasih atas kepercayaan Anda menggunakan layanan kami.\n\nHormat kami,\n[footer]\n\n_Ini adalah pesan otomatis, mohon tidak membalas pesan ini._",

    'payment_cancel' => "Yth. Bapak/Ibu [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah dibatalkan.\n\nRincian Tagihan:\nJumlah: [total]\nTanggal Invoice: [invoice_date]\nJatuh Tempo: [due_date]\nPeriode: [period]\n\nMohon segera melakukan pembayaran untuk menghindari gangguan layanan.\n\n_Pesan ini dikirim secara otomatis. Mohon tidak membalas langsung ke pesan ini._",

    'account_suspend' => "Yth. Pelanggan [full_name],\n\nLayanan internet Anda sementara ditangguhkan karena pembayaran invoice belum diterima.\n\nUntuk informasi lebih lanjut atau bantuan, silakan hubungi layanan pelanggan kami.\n\n[footer]",

    'account_active' => "Yth. Pelanggan [full_name],\n\nLayanan internet Anda telah berhasil diaktifkan.\n\nUsername: [pppoe_user]\nProfil: [pppoe_profile]\n\nSelamat menikmati layanan kami!\n\n_Pesan ini dikirim secara otomatis. Mohon tidak membalas langsung ke pesan ini_",

    'invoice_reminder' => "Halo [full_name],\n\nIni adalah pengingat untuk pembayaran Anda yang akan datang.\nID Pelanggan: [uid]\nNomor Invoice: #[no_invoice]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSilakan lakukan pembayaran sebelum jatuh tempo.\n\n[payment_gateway]\n\n[footer]",

    'invoice_overdue' => "Halo [full_name],\n\nInvoice Anda #[no_invoice] telah melewati jatuh tempo.\nID Pelanggan: [uid]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSegera lakukan pembayaran untuk menghindari suspend layanan.\n\n[payment_gateway]\n\n[footer]",
];
