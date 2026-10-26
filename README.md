# AG-Bescheinigungen für Freiwillige Feuerwehren

Kleine PHP-Webanwendung (mobile first) zum Erstellen von Arbeitgeberbescheinigungen nach einem Einsatz.

- **Alle Einsatzkräfte** pflegen nach dem Login ihre Arbeitgeberdaten und wählen, ob sie die Bescheinigung ausgedruckt und/oder per Mail bekommen.
- **Admins (Wehrführung)** geben zusätzlich Beginn und Ende eines Einsatzes ein, haken die Teilnehmer ab und erzeugen:
  - eine **Sammel-PDF** (eine Seite pro Person) für alle mit Druckwunsch
  - **einzelne PDFs per Mail** an alle mit Mailwunsch

  Dabei unterschreiben sie jedes Mal digital am Handy oder mit der Maus. Die Unterschrift wird nicht gespeichert, und Name und Funktion darunter stammen vom angemeldeten Admin. Außerdem verwalten Admins Personen, Arbeitgeber, Logo und Texte.

Alle Einsätze werden mit den ausgestellten PDFs archiviert:
- Admins sehen unter **Einsätze** die Historie. Dort können sie Druck-PDFs und einzelne Bescheinigungen erneut öffnen und Personen nachträglich erfassen, mit neuer Unterschrift.
- Jede Einsatzkraft sieht auf ihrer Startseite ihre letzten Bescheinigungen.

## Technik

- PHP ≥ 8.1, ohne Framework, mit den Erweiterungen `pdo_mysql`, `dom`, `mbstring` und `gd`
- MySQL/MariaDB
- [Dompdf](https://github.com/dompdf/dompdf) für die PDFs (HTML → PDF), [PHPMailer](https://github.com/PHPMailer/PHPMailer) für den SMTP-Versand. Beide liegen fertig in `vendor/`.

```
public/      Webroot (einzige öffentlich erreichbare Ebene)
src/         Logik (DB, Login, PDF, Mail, Texte)
templates/   header/footer
config/      config.php (nicht eingecheckt), config.sample.php
storage/     hochgeladenes Logo, bescheinigungen/ (PDF-Archiv), tmp/ (Schrift-Cache)
sql/         schema.sql
bin/         create-admin.php
```

## Installation (Plesk)

1. Das Repository in das Verzeichnis der Domain legen, z. B. `httpdocs/`.
2. In Plesk unter **Hosting-Einstellungen** das **Dokumentstammverzeichnis** auf `httpdocs/public` setzen. Die `.htaccess`-Dateien in den anderen Ordnern sind nur eine zusätzliche Absicherung.
3. Eine Datenbank anlegen und `sql/schema.sql` importieren, z. B. mit phpMyAdmin.
4. `config/config.sample.php` nach `config/config.php` kopieren und dort Datenbank, SMTP und `base_url` eintragen.
5. `storage/` mit allen Unterordnern muss für PHP beschreibbar sein: hochgeladenes Logo, `bescheinigungen/` mit dem PDF-Archiv und `tmp/`. Das **PDF-Archiv gehört ins Backup**, zusammen mit der Datenbank.
6. Den ersten Admin anlegen, per SSH oder über die geplanten Aufgaben in Plesk:
   ```
   php bin/create-admin.php wehrfuehrer@example.de Erika Musterfrau
   ```
   Ohne Passwort meldet sich der Admin über „Login-Link per Mail“ an und kann danach im Profil ein Passwort setzen.
7. Als Admin unter **Personen** die eigene Funktion eintragen (z. B. „Wehrführer“), dann unter **Einstellungen** Logo und Bescheinigungstext prüfen. Dazu gibt es die Vorschau-PDF. Dann unter **Personen** die Einsatzkräfte anlegen und ihnen einen Login-Link schicken.

## Lokale Entwicklung (ohne SMTP)

Lokal werden Mails als `.eml`-Dateien nach `storage/tmp/mails/` geschrieben, statt verschickt zu werden. Siehe `mail_dir` in der Konfiguration.

1. Einen lokalen MySQL starten, z. B. mit Podman oder Docker:
   ```
   podman run -d --name vda-mysql -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=vda -e MYSQL_USER=vda -e MYSQL_PASSWORD=vda docker.io/library/mysql:8.4
   ```
2. `config/config.php` mit dieser Datenbank anlegen und `mail_dir` setzen.
3. Schema und Testdaten einspielen. **Achtung: Das Skript löscht vorhandene Tabellen.** Danach gibt es den Admin `admin@test.de` mit Passwort `test1234`.
   ```
   php -d extension=pdo_mysql bin/dev-setup.php
   ```
4. Den Server starten und http://localhost:8080 öffnen:
   ```
   php -d extension=pdo_mysql -d extension=mbstring -d extension=gd -d extension=openssl -S localhost:8080 -t public
   ```

Ohne `php.ini` (z. B. PHP über winget) zusätzlich `-d extension_dir="<php-ordner>\ext"` angeben.

## Login

- Mit E-Mail und Passwort.
- Alternativ jederzeit mit einem **Login-Link per Mail**, der 30 Minuten gilt. Das geht auch, wenn ein Passwort gesetzt ist, und hilft so bei vergessenen Passwörtern.
- Admins können Passwörter unter **Personen** neu setzen oder löschen.

## Einstellungen und Texte

Unter **Einstellungen** stehen:
- Briefkopf und Logo
- der **Bescheinigungstext** als ein HTML-Text mit Editor (TinyMCE, vom CDN jsDelivr geladen)
- die Mailtexte

Alles wird in der Tabelle `settings` gespeichert. Die Standardwerte stehen in `src/settings.php`.

Platzhalter im Bescheinigungstext:
- `{arbeitgeber_adresse}`
- `{datum}`: das Erstelldatum
- `{vorname}`, `{nachname}`
- `{personalnummer}`, `{personalnummer_zusatz}`
- `{von_datum}`, `{von_uhrzeit}`, `{bis_datum}`, `{bis_uhrzeit}`
- `{unterschrift}`, `{unterzeichner}`, `{unterzeichner_funktion}`

Die Erklärungen dazu stehen direkt auf der Seite.

Platzhalter in den Mails:
- `{datum}`: das Einsatzdatum
- `{vorname}`, `{nachname}`
- `{link}`, `{minuten}`: nur in der Login-Mail
