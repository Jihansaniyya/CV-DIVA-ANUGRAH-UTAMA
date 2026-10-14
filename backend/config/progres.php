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

];
