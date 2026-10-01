# MOTORA

MOTORA is a native PHP and MariaDB rider community app for the existing XAMPP database `motora`.

## Modern rider community design

The UI uses a charcoal palette with electric-blue actions, light blue links and icons, Manrope typography, navigation with icons, rounded controls, and a photographic home banner. Layered dark surfaces distinguish content, inputs, and chat histories. The home page presents a personal welcome, account counts, community discovery, and the rider's garage. Login, registration, recovery, rider directories, communities, garages, profiles, settings, and messaging share the same visual system across desktop, tablet, and mobile.

Every page uses a shared, muted road-video background behind the content. The footage is a real motorcycle recording by [Timothy Miller on Pexels](https://www.pexels.com/video/man-riding-motorcycle-along-road-10439710/), uploaded in 2021, used under the [Pexels license](https://www.pexels.com/license/). It is stored locally as `assets/videos/road-motion.mp4` (720p, 24 fps, approximately 1.8 MB, no audio). `assets/images/road-motion.jpg` is a still from the same recording. No generated video is used. A dark overlay keeps text readable; panels remain opaque. A pause/play button remembers the visitor's choice. Reduced-motion preferences, data saving, unavailable playback, and JavaScript disabled all retain a static background, and hidden tabs pause playback. The background styles load last from `background-video.css`, and `background-video.js` manages playback.

`includes/layout.php` loads `assets/css/design.css` for the shared system and home, `components.css` for application pages, and `auth.css` for public entry pages. `community.css` provides group-chat behaviors before the component styling. The earlier `style.css` and `dashboard-theme.css` are no longer loaded. Fonts are stored locally in `assets/fonts/`, with their SIL Open Font License files, so the UI does not require an external font request.

The header keyword search supports rider names/usernames, motorcycles, and cities. It respects the existing profile, search, city, garage, and block privacy settings. Run `& 'C:\xampp\php\php.exe' tests/global-search.php` for its isolated regression cases, in addition to the existing tests below.

Art direction references: [Bike EXIF](https://www.bikeexif.com/) and [Return of the Cafe Racers](https://www.returnofthecaferacers.com/). The locally stored hero photo is sourced from [Unsplash](https://images.unsplash.com/photo-1558981806-ec527fa84c39). Fonts: [Barlow Condensed](https://github.com/google/fonts/tree/main/ofl/barlowcondensed) and [Manrope](https://github.com/google/fonts/tree/main/ofl/manrope).

## Run locally

1. Start Apache and MySQL in XAMPP.
2. The audited database was `motora`. Before migrating, its only table was `user`, with zero rows. The original table dump is in `database/backups/motora-user-before-motora.sql`.
3. Run `database/migration.sql` once against `motora` from phpMyAdmin, or from this project folder with:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' -u root motora -e 'source database/migration.sql'
   ```

   The migration is additive and repeatable. It keeps the existing `user` table and adds the missing MOTORA fields/tables; it does not create or drop a database, drop a table, or remove rows.
4. Apply the additive community chat migration with `& 'C:\xampp\mysql\bin\mysql.exe' -u root motora -e 'source database/community-chat.sql'`. It preserves existing communities and sets their creators to leader. The base migration must run first.
5. Open [http://localhost/project/project%20pak%20qibar/](http://localhost/project/project%20pak%20qibar/) and create an account.

The connection defaults to XAMPP's local MySQL at `127.0.0.1`, database `motora`, user `root`, with an empty password. Override those values with `MOTORA_DB_HOST`, `MOTORA_DB_NAME`, `MOTORA_DB_USER`, and `MOTORA_DB_PASS` environment variables when needed.

## Included features

- Registration, login, logout, and one-time password-reset links for local development. Reset links are not delivered by email in this build; optional development-only link display requires `MOTORA_LOCAL_RESET_LINKS=1` and a loopback localhost request, with at most five requests per account per hour.
- Rider profiles with privacy controls, searchable motorcycles, city/region filters, and pagination.
- Motorcycle garage CRUD and secure multi-image uploads.
- Community discovery, creation, membership, approval requests, and leaving.
- Friend requests, acceptance/rejection, blocking, and rider conversations with polling and unread status.
- Account settings, privacy preferences, notifications, CSRF validation, prepared SQL statements, and escaped output.
- Friend connections, pending requests, and blocked riders use independent pagination controls.

Run the regression checks with `& 'C:\\xampp\\php\\php.exe' tests/run.php` and `& 'C:\\xampp\\php\\php.exe' tests/review-static.php`.

Uploaded images are limited to JPG, PNG, and WEBP, capped at 5 MB each, and stored under `uploads/`. Passwords are stored with PHP's password hashing API.

## Community groups

Each community has one leader, admins, and members. The creator becomes leader automatically, including pre-existing communities after migration. The leader alone assigns roles, transfers leadership, closes a community, or permanently deletes it. Permanent deletion requires confirmation and removes memberships, chat history, calls, notifications, and unreferenced uploaded photos; it also works for closed communities. Admins approve and add members, remove ordinary members, moderate messages, and manage group permissions. Admins cannot remove the leader or another admin. Ordinary members follow the group's send, edit-info, invite, pin, and call permissions. Every permission is checked on the server.

Community chat includes text and photos, replies, six reactions, search, older-message pagination, read counts, notification muting, and up to three pinned messages with 24-hour, 7-day, or 30-day durations. Authors can edit their own messages within 15 minutes. Authors and admins can delete messages for everyone within two days; deleting for yourself hides the message only for that account. Private communities require a valid invite, and joining can require admin approval. Resetting an invite invalidates the old link. Closing a community stops new messages and calls while preserving member-readable history.

Photos are served through a membership-checked endpoint. Keep `uploads/group/.htaccess` installed and Apache `AllowOverride` enabled; on another server, deny direct HTTP access to `uploads/group/` as well.

Voice and video calls use WebRTC, with up to **8 simultaneous participants**, mute/unmute, camera on/off, leaving, and an admin action to end the call for everyone. Microphone/camera access requires user permission and either localhost or HTTPS. The browser connects media directly between participants. The default ICE configuration uses a public STUN server. For reliable calls between different networks, configure your own TURN service through `MOTORA_WEBRTC_ICE_SERVERS`, a JSON array of WebRTC ICE server objects (including `urls`, `username`, and `credential` where required). Keep TURN credentials short-lived in production. STUN alone cannot traverse every firewall/NAT. This implementation does not claim WhatsApp's end-to-end encrypted chat protocol or full WhatsApp feature parity.

The permission pattern is inspired by [WhatsApp group settings](https://faq.whatsapp.com/526742385997912/?cms_platform=web&locale=id_ID). Message limits follow [editing](https://faq.whatsapp.com/6614640168569481/?cms_platform=web) and [deleting](https://faq.whatsapp.com/1370476507114859/?cms_platform=web) behavior; leadership restrictions reflect MOTORA's requested three-role model.

Run `& 'C:\xampp\php\php.exe' tests/community.php` for isolated role, access, chat, invite, rendering, and call-signaling regression checks. Its tables are connection-local and do not change live rider data.
