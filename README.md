# Production Monitoring — Backend

REST API Production Monitoring System PT XYZ Manufacturing (Laravel 10 · PHP 8.2 · Nginx · MySQL 8).
Frontend dashboard: repo [avian_test_fe](https://github.com/hanzz96/avian_test_fe).

## 1. Menjalankan aplikasi dengan Docker

Prasyarat: Docker + Docker Compose v2.

1. Taruh dataset yang diberikan di `db/init/01-dataset.sql` (folder `db/` tidak ikut di git).
2. Jalankan:

```bash
docker compose up -d --build
```

Start pertama butuh beberapa menit (build image + import dataset). Cek dengan `docker compose ps`
sampai `manufacturing_mysql` berstatus `healthy`.

| Service | Container | Port host |
|---|---|---|
| Nginx | `manufacturing_nginx` | 8000 |
| PHP-FPM + Laravel | `manufacturing_app` | — |
| MySQL 8 | `manufacturing_mysql` | 3306 |

```bash
docker compose logs -f app   # log aplikasi
docker compose down          # stop (data tetap)
docker compose down -v       # stop + hapus database
```

## 2. Konfigurasi environment (`.env`)

File `.env` **opsional** — semua variabel punya nilai default. Untuk mengubahnya:
`cp .env.example .env`, edit, lalu `docker compose up -d`.

| Key | Default | Keterangan |
|---|---|---|
| `DB_DATABASE` | `manufacturing_test` | nama database |
| `DB_USERNAME` / `DB_PASSWORD` | `app` / `secret` | user aplikasi |
| `DB_ROOT_PASSWORD` | `root` | password root MySQL |
| `DB_FORWARD_PORT` | `3306` | port database di host |
| `APP_PORT` | `8000` | port web di host |
| `APP_ENV` | `local` | selain `production`, response error menyertakan `stacktrace` |

Di dalam container, `DB_HOST` otomatis `db` (diatur oleh compose).

## 3. Import database

Import berjalan **otomatis**: saat volume database masih kosong, MySQL menjalankan semua file di
`db/init/` (`01-dataset.sql` membuat database `manufacturing_test` beserta datanya).

- Import ulang dari nol: `docker compose down -v && docker compose up -d`
- Import manual ke container yang sudah jalan:
  ```bash
  docker exec -i manufacturing_mysql mysql -uroot -proot < db/init/01-dataset.sql
  ```

Struktur tabel dan isi data tidak diubah oleh aplikasi.

## 4. URL aplikasi & endpoint API

**URL aplikasi:** http://localhost:8000 · **Base URL API:** http://localhost:8000/api

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/dashboard` | Ringkasan global: `summary`, `trend_7_days`, `status_breakdown`, `top_machines` |
| GET | `/api/dashboard/machine/{id}` | Performa satu mesin (`{id}` = `machine_code`, mis. `GRD02`) |
| GET | `/api/production-orders` | List production order |
| POST | `/api/production-results` | Kirim hasil produksi untuk WO berstatus `RUNNING` |

`GET /api/production-orders` — query opsional: `search`, `product`, `machine`, `status` (boleh
beberapa, pisah koma), `date` (`YYYY-MM-DD`), `page`, `per_page`, `sort_by`, `sort_dir`.

`POST /api/production-results` — body JSON:

```json
{ "wo_number": "WO2026000009", "qty_good": 100, "qty_reject": 2, "production_date": "2026-01-05" }
```

Contoh cek cepat:

```bash
curl -H "Accept: application/json" http://localhost:8000/api/dashboard
```

Koleksi Insomnia siap impor: [`docs/insomnia.json`](docs/insomnia.json).
Catatan teknis dan keputusan perhitungan: [`docs/task.md`](docs/task.md).
