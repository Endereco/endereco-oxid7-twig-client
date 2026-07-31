# Endereco Implementierung für Oxid 7

## Installation

Die Installation erfolgt in folgenden Schritten:

1. Das Modul über Composer installieren

```bash
composer require endereco/endereco-oxid7-twig-client
```

Der Befehl lädt die neuste Version herunter. Um eine spezielle Version zu installieren, zum Beispiel *1.1.0*, kann
der Befehl folgenderweise angepasst werden.

```bash
composer require endereco/endereco-oxid7-twig-client:1.1.0
```

2. Migrationen ausführen

```bash
vendor/bin/oe-eshop-db_migrate migrations:migrate
```

[Siehe Dokumentation für Migrationen in Oxid 7](https://docs.oxid-esales.com/developer/en/7.2/development/tell_me_about/migrations.html)

3. Cache leeren und Views neuaufbauen

```bash
vendor/bin/oe-console oe:cache:clear
vendor/bin/oe-eshop-db_views_generate
```

4. Modul aktivieren

```bash
vendor/bin/oe-console oe:module:activate endereco-oxid7-client
```

5. Konfiguration vom Modul im Admin-Bereich vornehmen

---

## Lokaler Playground (Docker)

Mit `playground.sh` lässt sich ein lokaler OXID 7 Testshop starten, in dem das Modul aus dem
aktuellen Arbeitsverzeichnis live gemountet ist. Änderungen an PHP-Dateien sind sofort wirksam —
kein Image-Rebuild, kein Container-Neustart.

### Voraussetzungen

- Docker installiert und gestartet (Docker Desktop auf Mac/Windows, Docker Engine auf Linux)
- Port 80 ist frei (oder beim Start einen anderen Port wählen)

### Starten

```bash
./playground.sh
```

Das Skript fragt interaktiv nach:

- **OXID-Version** — `7.1`, `7.2`, `7.3`, `7.4` oder `7.5`
  (bestimmt die minimale PHP-Version: 8.1 / 8.2 / 8.2 / 8.2 / 8.3)
- **Image neu bauen?** — nur nötig, wenn sich `docker/Dockerfile` oder
  `docker/entrypoint.sh` geändert haben

Nach dem Start läuft im Hintergrund automatisch:

- OXID-Shop-Setup inkl. Demo-Daten und Admin-Account
- Endereco-Modul installieren, aktivieren, Migrationen ausführen
- Datenbank-Views neu aufbauen

### URLs und Zugangsdaten

    Storefront    http://localhost/
    Admin         http://localhost/admin/        admin@example.com / admin
    AdminNeo      http://localhost/adminneo/     Server: oxid7-<version>-db
                                                 Benutzer: user  Passwort: pwd  DB: db

AdminNeo läuft auf demselben Port wie der Shop (kein eigener Port), unter dem Pfad `/adminneo/`.
Das Skript gibt beim Start die vollständige AdminNeo-URL mit vorausgefüllten Verbindungsparametern
aus — nur das Passwort `pwd` muss manuell eingetippt werden.

Der Server-Name im Verbindungsformular lautet `oxid7-<version>-db`, also z. B. `oxid7-7.5-db`
für OXID 7.3.

Wenn beim Start ein anderer Port gewählt wurde, gilt er für alle drei URLs.

### API-Key hinterlegen

Das Modul ist nach dem Setup aktiv, aber ohne API-Key nicht funktionsfähig.
Den Key im Admin-Backend unter Extensions → Modules → Endereco Address-Services für Oxid → Settings → Access Data eintragen.

### Shell-Zugang und nützliche CLI-Befehle

Shell im laufenden Container öffnen:

```bash
docker exec -it oxid7-7.5 bash
```

Alle folgenden Befehle laufen innerhalb des Containers im Verzeichnis `/var/www/html`.

**Cache leeren** (nach Template- oder Konfigurationsänderungen):

```bash
vendor/bin/oe-console oe:cache:clear
```

**Datenbankmigrationen ausführen** (nach Branch-Wechsel mit Schemaänderungen):

```bash
vendor/bin/oe-eshop-doctrine_migration migrations:migrate
```

**Modul aktivieren / deaktivieren:**

```bash
vendor/bin/oe-console oe:module:activate   endereco-oxid7-client
vendor/bin/oe-console oe:module:deactivate endereco-oxid7-client
```

### Container stoppen

```bash
./playground.sh stop
```

Das Skript erkennt automatisch, welche Version gerade läuft. Laufen mehrere Versionen
gleichzeitig, erscheint ein kurzes Auswahlmenü.
