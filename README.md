# KopiCare — sistem pakar kopi Arabika

Aplikasi PHP + MySQL untuk demonstrasi implementasi **Forward Chaining** dan **Certainty Factor (CF)**. Data awal merupakan basis pengetahuan contoh, bukan hasil wawancara atau pemeriksaan kebun CV. Riyanti Berjaya. Nama perusahaan pada judul dapat disesuaikan saat penyusunan laporan.

## Deploy ke Vercel + Supabase

Vercel menjalankan aplikasi PHP ini melalui Docker dan FrankenPHP. File `Dockerfile` juga memasang driver PDO PostgreSQL. `supabase.sql` membuat tabel PostgreSQL dan mengisi basis pengetahuan awal; sesi login disimpan di tabel Supabase `app_sessions` agar tidak bergantung pada file sementara di server.

1. Buat project Supabase, buka **SQL Editor**, lalu jalankan seluruh isi `supabase.sql`.
2. Jika data XAMPP yang sudah ada ingin dipertahankan, jalankan `php tools/export_mysql_data_for_supabase.php` dari folder proyek lokal. Jalankan file `.private/supabase-data-import.sql` yang dihasilkan melalui SQL Editor Supabase. File tersebut berisi data pengguna dan hash kata sandi; jangan bagikan atau commit file itu.
3. Di Supabase pilih **Connect**, salin **Transaction pooler** connection string (port `6543`). Aplikasi menggunakan koneksi singkat dan emulasi prepared statement yang kompatibel dengan pooler. Encode karakter khusus di password sesuai format URL.
4. Buat repository GitHub dan push folder proyek ini. Jangan push file `.env`, folder `.private`, atau connection string.
5. Di Vercel pilih **Add New → Project**, import repository GitHub, dan pastikan Root Directory menunjuk ke folder yang berisi `Dockerfile`.
6. Tambahkan Environment Variables berikut di Vercel untuk **Production** (dan Preview bila dipakai):

   - `DB_DRIVER` = `pgsql`
   - `DATABASE_URL` = connection string Transaction pooler dari Supabase
   - `SESSION_STORE` = `database`

7. Deploy. Jika data XAMPP sudah diimpor, masuk menggunakan akun admin lama. Jika Supabase project dimulai tanpa impor data, buka `https://<domain-vercel>/admin/register.php` untuk membuat admin pertama. Akun petani dapat mendaftar melalui halaman **Register**.

Simpan connection string hanya di Environment Variables Vercel, jangan di GitHub atau kode PHP. `database.sql` tetap dipakai untuk XAMPP lokal (MySQL); gunakan `supabase.sql` untuk project Supabase (PostgreSQL). Langkah ekspor data opsional di atas mempertahankan akun, aturan, dan riwayat diagnosis lokal.

### Catatan penyimpanan sesi

Di Vercel, sesi PHP memakai tabel Supabase karena filesystem container tidak permanen dan tidak dibagi ke semua instance. Jangan menghapus tabel `app_sessions` selama aplikasi digunakan.

## Menjalankan lokal

1. Jalankan Apache dan MySQL dari XAMPP/Laragon.
2. Buka phpMyAdmin dan impor `database.sql` (membuat database `kopi_cf` beserta tabel dan data awal).
3. Sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` di `config.php` bila konfigurasi berbeda.
4. Atur document root/virtual host agar menunjuk langsung ke folder proyek, lalu buka alamat lokal tersebut. Contoh: virtual host `http://kopicare.test/` dengan document root folder proyek. Tautan memakai root URL `/`; jangan pasang sebagai subfolder `/kopicare` tanpa menyesuaikan base URL.
5. Daftar akun petani melalui **Register**. Untuk admin, buka **Panel admin → Register admin**. Admin pertama dapat mendaftar tanpa login; setelah dibuat, akun admin berikut hanya bisa ditambahkan oleh admin yang sedang login.

## Alur inferensi

- **Forward Chaining:** gejala umum yang dipilih dicocokkan dengan fakta pada tabel `rules`; penyakit yang memiliki aturan terkait menjadi kandidat. Setelah itu sistem menampilkan gejala khusus setiap kandidat untuk ditanyakan.
- **Certainty Factor:** keyakinan pengguna dipetakan ke `tidak=0`, `tidak yakin=0,2`, `sedikit=0,4`, `cukup=0,6`, `yakin=0,8`, `sangat yakin=1`. CF gejala = CF pengguna × CF pakar. Bukti positif digabung berulang dengan `CFgabung = CF1 + CF2 × (1 − CF1)`. Angka persentase adalah skor sistem untuk membandingkan kandidat, bukan probabilitas klinis terkalibrasi.
- Data aturan dan nilai CF pakar bisa ditambah, diubah, dan dihapus melalui panel admin. Data penyakit/gejala yang terhubung aturan memakai foreign key cascade.

## Data awal dan rujukan

Gejala dan daftar awal penyakit disusun untuk prototipe dengan merujuk sumber terbitan 2024–2025 berikut. Sumber lama hanya disebut sebagai rujukan pelengkap untuk pengenalan gejala khas; nilai CF awal disusun sebagai contoh dan **bukan nilai hasil elicitation pakar**.

- Embrapa Café (2024), *Manejo integrado de pragas e doenças do café arábica*, [rekaman publikasi resmi](https://www.infoteca.cnptia.embrapa.br/infoteca/handle/doc/1167902).
- *Coffee Leaf Rust (Hemileia vastatrix) Disease in Coffee Plants and Perspectives by the Disease Control* (2024), [review ilmiah](https://www.sciencedirect.com/org/science/article/pii/S0031945724000078).
- *First Occurrence of Coffee Leaf Rust Caused by Hemileia vastatrix on Coffee in Saudi Arabia* (2024), [Plant Disease Management Reports](https://www.mdpi.com/2036-7481/15/1/11).
- *Coffee wilt disease: The forgotten threat to coffee* (2024), [Plant Pathology review](https://bsppjournals.onlinelibrary.wiley.com/doi/full/10.1111/ppa.13833).
- *Diversity and Pathogenicity of Colletotrichum Species Causing Coffee Anthracnose in China* (2025), [artikel akses terbuka](https://pmc.ncbi.nlm.nih.gov/articles/PMC11946238/).
- Rujukan pelengkap deskripsi bercak Cercospora dan penyakit buah Arabika: [FAO Arabica coffee manual](https://www.fao.org/4/ae939e/ae939e0b.htm), [Arabica Coffee Handbook](https://nov.ugandacoffee.go.ug/sites/default/files/2022-03/Arabic%20Coffee%20Handbook.pdf).

## Catatan untuk penggunaan nyata

Sebelum digunakan sebagai alat bantu keputusan kebun, verifikasi nama/diagnosis penyakit lokal serta bobot CF dengan ahli perlindungan tanaman dan data CV. Riyanti Berjaya. Beberapa gejala tumpang tindih; hasil perlu pemeriksaan lapang.
