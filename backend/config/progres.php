<?php

return [

    /*
    | Batas unggah foto bukti progres.
    |
    | Batas efektif per foto adalah nilai terkecil antara `foto_maks_kb` dan `upload_max_filesize`
    | PHP; total satu permintaan dibatasi `post_max_size` PHP. Lihat ProgressUploadLimits.
    */

    'foto_maks_kb' => (int) env('PROGRES_FOTO_MAKS_KB', 5120),

    'foto_maks_jumlah' => (int) env('PROGRES_FOTO_MAKS_JUMLAH', 10),

    /*
    | Jumlah baris pekerjaan maksimal dalam satu laporan progres. Setiap baris wajib memiliki minimal
    | satu foto bukti saat dikirim, jadi nilai ini sebaiknya tidak melebihi `foto_maks_jumlah`.
    */

    'pekerjaan_maks' => (int) env('PROGRES_PEKERJAAN_MAKS', 10),

    /*
    | Jumlah kendala maksimal dalam satu laporan progres. Satu kendala boleh mencakup beberapa pekerjaan;
    | yang dihitung adalah kendala dengan isian (jenis, uraian, tindak lanjut) yang berbeda.
    */

    'kendala_maks' => (int) env('PROGRES_KENDALA_MAKS', 5),

];
