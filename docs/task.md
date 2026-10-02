# Production Monitoring — Catatan Teknis Backend

REST API untuk **Production Monitoring System PT XYZ Manufacturing** (Technical Test Software Engineer).
Frontend dashboard ada di repo terpisah: [avian_test_fe](https://github.com/hanzz96/avian_test_fe).

> **Aturan test:** struktur tabel dan isi data **tidak boleh diubah**. Itulah sebabnya project ini
> tidak memakai migration/seeder — model Eloquent hanya memetakan tabel yang sudah ada.

## Tech stack

Laravel 10 · PHP 8.2 (FPM) · Nginx · MySQL 8 — semuanya via Docker Compose.

## 1. Menjalankan dengan Docker

Prasyarat: Docker + Docker Compose v2. Tidak perlu PHP/Composer di mesin Anda.

```bash
# (a) Taruh dataset yang diberikan (tidak ikut di git) di:
#     db/init/01-dataset.sql

# (b) Build dan jalankan semuanya
docker compose up -d --build
```

Start pertama butuh beberapa menit (build image + import dataset ±2 MB). Pantau dengan
`docker compose ps` — tunggu `manufacturing_mysql` berstatus `healthy`.

| Service | Container | Fungsi | Port host |
|---|---|---|---|
| `nginx` | `manufacturing_nginx` | web server | **8000** |
| `app` | `manufacturing_app` | PHP-FPM + Laravel | — |
| `db` | `manufacturing_mysql` | MySQL 8 | **3306** |

**URL aplikasi:** `http://localhost:8000` · **Base URL API:** `http://localhost:8000/api`
(cek cepat: `curl -H "Accept: application/json" http://localhost:8000/api/dashboard`).

Perintah yang berguna:

```bash
docker compose logs -f app      # log PHP
docker compose down             # stop (data tetap)
docker compose down -v          # stop + hapus database (import ulang saat up berikutnya)
```

## 2. Konfigurasi environment (`.env`)

Docker **tidak mewajibkan** file `.env` — semua punya nilai default. Untuk mengubahnya, `cp .env.example .env`
lalu ubah; Docker Compose membacanya otomatis.

| Key | Default | Keterangan |
|---|---|---|
| `DB_DATABASE` | `manufacturing_test` | nama database (dibuat oleh dataset) |
| `DB_USERNAME` / `DB_PASSWORD` | `app` / `secret` | user aplikasi (password dev lokal) |
| `DB_ROOT_PASSWORD` | `root` | password root MySQL |
| `DB_FORWARD_PORT` | `3306` | port database di host (ubah bila bentrok) |
| `APP_PORT` | `8000` | port web di host |
| `APP_ENV` | `local` | selain `production`, error API menyertakan `stacktrace` |

Di dalam container, host database otomatis `db` (di-set oleh compose), jadi `DB_HOST` di `.env` hanya
berlaku bila Anda menjalankan Laravel di luar Docker.

## 3. Import database

Import **otomatis**: saat volume database masih kosong, MySQL menjalankan semua file di `db/init/`
(`01-dataset.sql` membuat database `manufacturing_test` beserta datanya). Tidak ada langkah manual
selain menaruh file dataset sebelum `docker compose up`.

- Import ulang dari nol: `docker compose down -v && docker compose up -d`
- Import manual ke container yang sudah jalan:
  ```bash
  docker exec -i manufacturing_mysql mysql -uroot -proot < db/init/01-dataset.sql
  ```
- Data disimpan di Docker volume `mysql_data` (bukan di folder project); file di `db/init/` hanya dibaca
  saat volume kosong.

> Aturan test: struktur tabel dan isi data **tidak boleh diubah**, karena itu tidak ada migration/seeder;
> model Eloquent hanya memetakan tabel yang sudah ada.

## Alternatif: tanpa Docker untuk PHP

Hanya database di Docker, Laravel jalan di mesin lokal (PHP 8.1+, Composer):

```bash
docker compose up -d db
cp .env.example .env && composer install && php artisan key:generate
php artisan serve               # http://127.0.0.1:8000/api
```

## Endpoint API

Base URL: `http://localhost:8000/api`. Koleksi siap pakai untuk Insomnia: [`docs/insomnia.json`](docs/insomnia.json).

| Method | Path | Fungsi |
|---|---|---|
| GET | `/dashboard` | Ringkasan global: `summary`, `trend_7_days`, `status_breakdown`, `top_machines` |
| GET | `/dashboard/machine/{id}` | Performa satu mesin. `{id}` = `machine_code` (mis. `GRD02`) |
| GET | `/production-orders` | List production order (filter, search, sort, pagination) |
| POST | `/production-results` | Kirim hasil produksi untuk WO berstatus `RUNNING` |

**`GET /production-orders`** — query string (semua opsional):
`search` (WO / produk / mesin / operator), `product` (kode produk), `machine` (kode mesin),
`status` (`RUNNING,OPEN,…` boleh lebih dari satu), `date` (`YYYY-MM-DD`, tanggal `plan_start`),
`page`, `per_page` (maks 100), `sort_by`, `sort_dir` (`asc`/`desc`).

**`POST /production-results`** — body JSON:
```json
{ "wo_number": "WO2026000009", "qty_good": 100, "qty_reject": 2, "production_date": "2026-01-05" }
```
`qty_*` ≥ 0, `production_date` tidak boleh melebihi hari ini, dan WO harus `RUNNING`.

## Format error

Semua error `api/*` dirender terpusat di [`app/Exceptions/Handler.php`](app/Exceptions/Handler.php):

| Kasus | Status | Body |
|---|---|---|
| `throw` turunan `CustomException` (error yang memang untuk user) | 400 | `{"message": "<pesan exception>"}` |
| Error tak terduga (bug, DB down, dst) | 500 | `{"message": "Internal Server Error"}` |
| Validasi gagal | 422 | `{"message", "errors": {...}}` |
| Route/data tidak ditemukan | 404 | `{"message": "..."}` |

Di luar `production`, setiap body ditambah key `stacktrace` (hanya frame kode aplikasi, tanpa `vendor/`).
Untuk error validasi ditambah key `source`: controller action + FormRequest (file dan baris `rules()`) yang menolak input.
Untuk membuat error baru yang ditampilkan ke user: `class XxxException extends CustomException`
(contoh: [`ProductionOrderNotRunningException`](app/Exceptions/ProductionOrderNotRunningException.php)).

## Struktur kode

Alur satu request: `routes/api.php` → **Controller** (tipis) → **Service** (logika bisnis) → **Model** (akses tabel).

```
app/
├── Exceptions/      Handler (error terpusat), CustomException + turunannya
├── Http/
│   ├── Controllers/ terima request → panggil service → balas JSON (tanpa query/logika)
│   └── Requests/    validasi input (FormRequest)
├── Models/          satu model per tabel (WorkOrder, Machine, ProductionResult, ...); scope & relasi
└── Services/
    ├── DashboardService.php         agregasi dashboard & detail mesin
    ├── ProductionOrderService.php   list WO: filter, sort, pagination
    └── ProductionResultService.php  simpan hasil produksi + aturan WO harus RUNNING
routes/api.php       daftar endpoint
docs/insomnia.json   koleksi Insomnia
```

Catatan model: tabel milik test tidak punya `created_at/updated_at` dan strukturnya tidak boleh diubah,
jadi semua model mewarisi `BaseModel` (tanpa timestamps). Agregasi (`SUM`, `GROUP BY`) memakai `selectRaw`
di atas model agar dihitung di database.

## Keputusan perhitungan (penting dibaca)

- **Achievement** = `good_qty ÷ target_qty × 100`, dibulatkan 2 desimal. Target hanya dihitung dari
  WO yang **sudah punya hasil produksi** (selaras dengan contoh di soal).
- **"Hari ini"** di dashboard = `MAX(actual_start)` pada `production_result`, **bukan** `CURDATE()`,
  agar hasil tidak bergantung kapan test dijalankan.
- `trend_7_days` selalu 7 tanggal berurutan; tanggal tanpa produksi tampil dengan nilai 0.
- `POST /production-results` hanya mewajibkan field di atas; `actual_start`, `actual_finish`,
  `runtime_minutes`, dan `achievement` diturunkan dari jadwal WO (boleh dikirim manual: `actual_start`, `actual_finish`).

## Status pengerjaan

- [x] Soal 5 — REST API
- [x] Soal 6 — Dashboard (repo FE)
- [ ] Soal 1–4 — Query SQL
- [x] Soal 7 — Docker (PHP + Nginx + MySQL) dan README
