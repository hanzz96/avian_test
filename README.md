# Production Monitoring — Backend (Laravel 10)

REST API untuk **Production Monitoring System PT XYZ Manufacturing** (Technical Test Software Engineer).
Frontend dashboard ada di repo terpisah: [avian_test_fe](https://github.com/hanzz96/avian_test_fe).

> **Aturan test:** struktur tabel dan isi data **tidak boleh diubah**. Itulah sebabnya project ini
> tidak memakai migration/seeder maupun model Eloquent — semua query langsung ke tabel yang sudah ada
> lewat Query Builder.

## Tech stack

Laravel 10 · PHP 8.1+ · MySQL 8 (Docker)

## Quick start

Prasyarat: PHP 8.1+, Composer, Docker.

```bash
# 1. Taruh dataset yang diberikan (tidak ikut di git) di sini:
#    db/init/01-dataset.sql

# 2. Jalankan MySQL — dataset otomatis ter-import saat start pertama
docker compose up -d

# 3. Setup Laravel
cp .env.example .env
composer install
php artisan key:generate

# 4. Jalankan API  → http://127.0.0.1:8000/api
php artisan serve
```

Cek koneksi: `GET http://127.0.0.1:8000/api/dashboard` harus mengembalikan JSON.

### Konfigurasi `.env`

| Key | Nilai default | Keterangan |
|---|---|---|
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | MySQL dari `docker-compose.yml` |
| `DB_DATABASE` | `manufacturing_test` | dibuat oleh dataset |
| `DB_USERNAME` / `DB_PASSWORD` | `app` / `secret` | user dev lokal (root password: `root`) |
| `APP_ENV` | `local` | selain `production`, error API menyertakan `stacktrace` |

### Database di Docker

- Data MySQL disimpan di Docker volume `mysql_data` (bukan di folder project).
- `docker compose down` → data tetap. `docker compose down -v` → data dihapus dan dataset di-import ulang.
- File di `db/init/` hanya dibaca saat volume masih kosong.

## Endpoint API

Base URL: `http://127.0.0.1:8000/api`. Koleksi siap pakai untuk Insomnia: [`docs/insomnia.json`](docs/insomnia.json).

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

Di luar `production`, setiap body ditambah key `stacktrace`.
Untuk membuat error baru yang ditampilkan ke user: `class XxxException extends CustomException`
(contoh: [`ProductionOrderNotRunningException`](app/Exceptions/ProductionOrderNotRunningException.php)).

## Struktur kode

```
app/
├── Exceptions/      Handler (error terpusat), CustomException + turunannya
├── Http/
│   ├── Controllers/ tipis: terima request → panggil service/query → balas JSON
│   └── Requests/    validasi input (FormRequest)
└── Services/
    └── DashboardService.php   query & kalkulasi dashboard
routes/api.php       daftar endpoint
docs/insomnia.json   koleksi Insomnia
```

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
- [ ] Soal 7 — Docker penuh (PHP + Nginx + MariaDB) dan README final
