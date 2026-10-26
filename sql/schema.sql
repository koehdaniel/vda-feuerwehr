-- Datenbankschema AG-Bescheinigungen (MySQL/MariaDB)

CREATE TABLE IF NOT EXISTS employers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    zusatz     VARCHAR(200) NOT NULL DEFAULT '',
    strasse    VARCHAR(200) NOT NULL DEFAULT '',
    plz        VARCHAR(10)  NOT NULL DEFAULT '',
    ort        VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nachname       VARCHAR(100) NOT NULL,
    vorname        VARCHAR(100) NOT NULL,
    email          VARCHAR(190) NULL,
    -- zusätzliche Adresse (z. B. dienstlich), bekommt Bescheinigungen in CC; nicht für den Login
    email_cc       VARCHAR(190) NULL,
    password_hash  VARCHAR(255) NULL,
    -- vom Benutzer gewünschte neue E-Mail, gilt erst nach Klick auf den Bestätigungslink
    email_neu           VARCHAR(190) NULL,
    email_token_hash    CHAR(64)     NULL,
    email_token_expires DATETIME     NULL,
    is_admin       TINYINT(1)   NOT NULL DEFAULT 0,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    -- erscheint unter der Unterschrift, z. B. "Wehrführer"
    funktion       VARCHAR(100) NOT NULL DEFAULT '',
    -- kein Arbeitgeber (NULL): Anschrift bleibt auf der Bescheinigung frei
    employer_id    INT UNSIGNED NULL,
    personalnummer VARCHAR(50)  NULL DEFAULT NULL,
    want_print     TINYINT(1)   NOT NULL DEFAULT 1,
    want_mail      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT fk_users_employer FOREIGN KEY (employer_id) REFERENCES employers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    UNIQUE KEY uq_login_tokens_hash (token_hash),
    CONSTRAINT fk_login_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS einsaetze (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    von        DATETIME     NOT NULL,
    bis        DATETIME     NOT NULL,
    stichwort  VARCHAR(200) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_einsaetze_von (von),
    CONSTRAINT fk_einsaetze_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eine Zeile pro ausgestellter Bescheinigung. Die PDFs liegen in storage/bescheinigungen/<einsatz_id>/.
CREATE TABLE IF NOT EXISTS einsatz_personen (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    einsatz_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    signed_by   INT UNSIGNED NULL,
    pdf_datei   VARCHAR(200) NOT NULL,
    -- Sammel-PDF, in der die Seite zum Ausdrucken steckt (NULL = nur per Mail)
    druck_datei VARCHAR(200) NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'ok',
    status_text VARCHAR(500) NOT NULL DEFAULT '',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_einsatz_personen (einsatz_id, user_id),
    CONSTRAINT fk_ep_einsatz FOREIGN KEY (einsatz_id) REFERENCES einsaetze (id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_signed_by FOREIGN KEY (signed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Texte für PDF und Mails. Fehlende Schlüssel fallen auf die Standardwerte in src/settings.php zurück.
CREATE TABLE IF NOT EXISTS settings (
    k VARCHAR(64) NOT NULL PRIMARY KEY,
    v TEXT        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
