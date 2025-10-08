<?php
return [
    'invoice_terbit' => "Salam [full_name]\n\nKami informasikan bahwa invoice Anda telah terbit dan dapat segera dibayarkan. Berikut rinciannya:\nID Pelanggan: [uid]\nNomor Invoice: [no_invoice]\nJumlah: Rp [amount]\nPPN: [ppn]\nDiskon: [discount]\nTotal: Rp [total]\nLayanan: Internet [pppoe_user] - [pppoe_profile]\nJatuh Tempo: [due_date]\nPeriode: [period]\nMohon segera lakukan pembayaran sebelum jatuh tempo.\n\nTerima kasih.\n[footer]\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

    'payment_paid' => "Halo [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah berhasil diproses.\nJumlah: [total]\nLayanan: [pppoe_user] - [pppoe_profile]\nPeriode: [period]\nMetode Pembayaran: [payment_gateway]\n\nTerima kasih atas pembayaran Anda.\n\n[footer]",

    'payment_cancel' => "Halo [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah dibatalkan.\nJumlah Tagihan: [total]\nTanggal Invoice: [invoice_date]\nJatuh Tempo: [due_date]\nPeriode: [period]\n\nSilakan lakukan pembayaran untuk menghindari gangguan layanan.\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

    'account_suspend' => "Pelanggan yang Terhormat,\n\nLayanan internet Anda sementara ditangguhkan karena invoice belum dibayarkan.\n\nSilakan hubungi layanan pelanggan kami untuk bantuan.\n\n[footer]",

    'account_active' => "Pelanggan yang Terhormat,\n\nLayanan internet Anda telah diaktifkan.\nUsername: [pppoe_user]\nProfil: [pppoe_profile]\n\nNikmati layanan kami!\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

    'invoice_reminder' => "Halo [full_name],\n\nIni adalah pengingat untuk pembayaran Anda yang akan datang.\nID Pelanggan: [uid]\nNomor Invoice: #[no_invoice]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSilakan lakukan pembayaran sebelum jatuh tempo.\n\n[payment_gateway]\n\n[footer]",

    'invoice_overdue' => "Halo [full_name],\n\nInvoice Anda #[no_invoice] telah melewati jatuh tempo.\nID Pelanggan: [uid]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSegera lakukan pembayaran untuk menghindari suspend layanan.\n\n[payment_gateway]\n\n[footer]",
];
