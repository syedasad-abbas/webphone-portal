## WebPhone Portal Stack

This repository contains an end-to-end, containerised reference implementation for the WebPhone portal that satisfies the user provisioning and calling workflow you described:

- **Laravel** powers the browser experience for both administrators (user provisioning) and end users (web dialer).
- **Node.js** exposes the secure REST API that writes to Postgres, enforces permissions/groups, and originates calls through FreeSWITCH.
- **PostgreSQL** persists all entities (admins, users, permission groups, carriers, call logs).
- **FreeSWITCH** handles carrier trunks and executes originate commands received from the backend.

Everything runs through `docker-compose` so that you can bring the system up with one command once the `.env` file is prepared.

---

### Service Layout

| Service | Path | Description |
| --- | --- | --- |
| `webportal` | `webportal/` | PHP 8.2 FPM container that installs Laravel 10, overlays the controllers/views in `webportal/laravel/`, and exposes FPM on port `9000`. |
| `web` | `webportal/nginx.conf` | Minimal Nginx container that serves the Laravel public directory and proxies requests to the FPM container. |
| `backend` | `backend/` | Node 20 + Express API (`src/server.js`) exposing `/admin`, `/auth`, and `/calls` endpoints. Talks to Postgres and FreeSWITCH. |
| `db` | `db/init.sql` | PostgreSQL 15 instance initialised with all required tables plus extensions. |
| `freeswitch` | `freeswitch/` | FreeSWITCH 1.10.9 configured with an open Event Socket (password `ClueCon` by default) and a simple default dial plan for API initiated calls. |

All services share the `core` Docker network for simple hostnames (`backend`, `db`, `freeswitch`, etc.).

---

### Data & Business Rules

* `db/init.sql` provisions the schema for groups, carriers, carrier prefixes, users, and call logs (including recording paths and the originating FreeSWITCH `call_uuid`).
* `backend/src/services/bootstrapService.js` runs each time the Node container boots to guarantee that:
  * A default permission group (name + permissions supplied via env) exists.
  * A default carrier exists with a root prefix and caller ID.
  * An administrator account (email/password from env) exists.
* Every new user created through `POST /admin/users` automatically inherits the default permission group, default carrier, and has `recording_enabled = true` unless you specify overrides. Admins can now edit or delete users via the portal UI, so you can change carriers, permission groups, or recording policies at any time.
* When a user dials, the backend automatically includes an `execute_on_answer=record_session` instruction if that account has recording enabled; FreeSWITCH writes the audio file to `${FREESWITCH_RECORDINGS_PATH}`, the resulting file path is persisted on the corresponding `call_logs` row, and the call’s FreeSWITCH UUID is stored so you can drive follow-up controls (mute/hangup/etc.).
* Carriers can be extended with custom prefixes + caller IDs via `/admin/carriers/:id/prefixes`.
* When a carrier requires registration, the backend renders a dedicated gateway XML file under `${FREESWITCH_GATEWAY_PATH}` (shared with the FreeSWITCH container), forces `reloadxml` + `sofia profile <profile> rescan`, and then issues `sofia profile <profile> register <gateway>`. Registration results are exposed in the UI so you immediately see a `200 OK` (or the exact error) for each carrier.
* The `/calls` endpoint validates the requesting JWT (user role), looks up that user’s carrier information, and issues a `bgapi originate` over the FreeSWITCH event socket. Each attempt is logged in `call_logs`.

---

### Laravel Web Portal Highlights

* Routes are declared in `webportal/laravel/routes/web.php`.
* Custom controllers in `app/Http/Controllers` call the backend API via Laravel’s HTTP client.
* Middleware (`EnsureAdminAuthenticated`, `EnsurePortalUser`) guard the admin dashboard and user dialer routes.
* Blade views live under `webportal/laravel/resources/views/` and use a lightweight Pico.css layout for quick styling.
* Environment variables (see `config/services.php`) point the portal at the backend URL configured in `.env`.

---

### Node.js Backend Highlights

* Express server defined in `src/server.js`.
* Route folders split by concern: `routes/admin.js`, `routes/auth.js`, `routes/calls.js`.
* Services encapsulate business logic and database interactions using the `pg` pool (`src/services/*.js`).
* `src/lib/freeswitch.js` owns the raw TCP communication with FreeSWITCH’s event socket (`8021/tcp`).
* JWT authentication middleware ensures only admins hit provisioning endpoints and users can only dial through `/calls`.

---

### FreeSWITCH Configuration

* Carriers now capture SIP transport details (`sip_domain`, `sip_port`, `transport`) and optional registration credentials so each PSTN gateway can be fully provisioned from the UI.
* `freeswitch/conf/autoload_configs/event_socket.conf.xml` opens the event socket on `0.0.0.0:8021` with password `ClueCon`.
* `freeswitch/conf/dialplan/default.xml` contains a basic context that logs the call and bridges to `user/${destination_number}`. Adapt this to fit your SIP profiles or gateways (e.g., point to a trunk or WebRTC endpoint).
* `freeswitch/recordings/` is bind-mounted into the container at `${FREESWITCH_RECORDINGS_PATH}` (default `/var/recordings`) so the audio files created by the automatic recordings are persisted on the host.
* `freeswitch/conf/sip_profiles/external/` is also bind-mounted into the backend container at `${FREESWITCH_GATEWAY_PATH}` so carrier registrations can be provisioned by simply creating/updating XML gateway files from Node.js.
* The Event Socket listener binds to `0.0.0.0:8021` and uses a permissive ACL (`allow_coders`) so the Node backend can originate calls and drive mid-call controls.
* If you need SIP trunks or gateways, add them under `conf/sip_profiles/` and point the carrier’s domain/port at that trunk. The backend now builds SIP URIs dynamically (e.g., `sofia/external/18005551212@sip.provider.com:5060`) so no prefix value is required.

---

### Getting Started

1. **Copy and edit environment variables**
   ```bash
   cp .env.example .env
   ```
   Adjust:
   * Database credentials if needed.
   * `BACKEND_DEFAULT_*` values (admin email/password, default group, carrier caller ID/domain/port/transport).
   * `BACKEND_BASE_URL` / `WEB_PORTAL_URL` when deploying remotely.
   * `APP_KEY` – run `php -r "echo base64_encode(random_bytes(32));"` and prefix it with `base64:` or let Laravel generate one later and paste it here.
   * `FREESWITCH_RECORDINGS_PATH` if you want recordings stored somewhere else inside the FreeSWITCH container (ensure the docker-compose bind mount matches).
   * `FREESWITCH_GATEWAY_PATH` (defaults to `/var/lib/freeswitch/gateways`) so the backend knows where to write gateway XML files, and `FREESWITCH_SIP_PROFILE` if you use a different profile name than `external`.

2. **Build and start the stack**
   ```bash
   docker-compose up --build
   ```
   The first build will take a little longer because the Laravel container runs `composer create-project`.

3. **Access the services**
   * Laravel portal: http://localhost:8080 (`/dialer` opens the live call window with mute/unmute/speaker/end controls once a call is queued)
   * Backend API: http://localhost:4000 (health check at `/health`)
   * PostgreSQL: localhost:5432 (credentials from `.env`)
   * FreeSWITCH: SIP on `5060/5080` (UDP/TCP), ESL on `8021/tcp`

4. **Login + usage flow**
   * Browse to `/admin/login`, authenticate with the default admin credentials you set, and provision users. The UI automatically fetches available groups and carriers, and records toggle defaults.
   * Users browse to `/login`, authenticate, and land on the WebPhone dialer (`/dialer`) where they can place calls. All calls are sent to the backend which originates them over FreeSWITCH.

---

### Testing & Extensibility

* Extend permission logic by enriching the `permissions` JSON stored for each group/user and checking it inside `backend/src/middleware/auth.js`.
* Add more admin panels by creating new Blade views + controllers that call backend endpoints.
* Enhance telephony flows by editing the FreeSWITCH dial plan or by integrating a media server.
* All services expose code volumes, so you can iterate locally and rebuild the individual service with `docker-compose build backend` (or `webportal`, `freeswitch`) as needed.

---

### Notes

* FreeSWITCH is provided with a permissive sample configuration. Before going to production, lock down SIP profiles, change the Event Socket password, and apply ACLs/firewall rules.
* The Laravel container currently generates its own `APP_KEY` during the image build. The compose environment variable still overrides that value, so ensure it matches your deployment needs.
* Recording is always enabled at the data layer; integrate with FreeSWITCH record applications if you need actual media capture.
* Because dependencies download during the Docker builds, an active internet connection is required the first time you run `docker-compose up --build`.
