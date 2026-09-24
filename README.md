# AMM

Plataforma SaaS para clubes de motociclistas. La primera implementación cubre Docker, Laravel 12, Vue 3, Inertia, PWA, autenticación, multi-organización, personas, miembros, motocicletas, documentos, roles y permisos. El OCR queda preparado por colas e interfaces, sin proveedor real todavía.

## Requisitos

- Docker Desktop
- Docker Compose

No se necesita PHP, MySQL, Redis, Apache ni XAMPP instalados en el host.

## Arranque

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

La aplicación queda en [http://localhost:8080](http://localhost:8080).

### Cuentas de demostración

| Usuario | Contraseña | Rol |
| --- | --- | --- |
| director@amm.test | password | Director / admin de plataforma |
| luis@amm.test | password | Miembro de AMM Cartagena |

Los datos de inscripción de Luis Fernando Romero Ramos y la Hero Hunk FI `XZZ01G` quedan cargados desde el seeder, tomados del formato de inscripción AMM Cartagena.

## Comandos

```bash
docker compose up -d
docker compose down
docker compose logs -f
docker compose exec app php artisan migrate
docker compose exec app php artisan db
docker compose exec app php artisan queue:work redis
docker compose exec app php artisan test
```

Vite (hot reload) corre en el servicio `vite` sobre el puerto `5173`.

## Arquitectura

```
Usuario → Nginx → PHP-FPM (Laravel + Inertia)
                ↘ MySQL 8.4
                ↘ Redis (cache, sesión, queues, rate limit)
Queue worker → Jobs OCR / notificaciones
Scheduler → vencimientos diarios
```

Los contenedores se hablan por red interna:

- Laravel → `mysql:3306`
- Laravel → `redis:6379`
- Nginx → `app:9000`

## Multi-tenant

Una organización es un club. Una persona puede pertenecer a varios clubes mediante `organization_members`. Los roles viven por organización en `organization_user_roles`. Las consultas de personas, documentos, motos y roles se aíslan por `organization_id` en policies, middleware y route bindings.

## Documentos

Los archivos sensibles se guardan en disco privado (`storage/app/private/documents`), compatible después con Amazon S3, Cloudflare R2 o MinIO. Nunca se usan en `public/storage`.

El flujo OCR futuro es:

`upload → document_scan → queue job → provider → extraction → needs_review → confirmación → document`

En esta fase el job solo deja el escaneo en `needs_review`.

## Stack

- Backend: Laravel 12, PHP 8.3, Sanctum, Queues, Policies, Events, Jobs, Notifications
- Frontend: Vue 3, TypeScript, Inertia.js, Tailwind CSS, PWA
- Datos: MySQL 8.4, utf8mb4_unicode_ci, InnoDB, ULID, foreign keys
- Infra: Docker, Nginx, Redis
