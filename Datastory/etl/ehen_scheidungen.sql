-- Datenmodell für die Datastory "Ehen und Scheidungen".
--
-- Einmal in phpMyAdmin ausführen. Danach füllt etl/load.php die Tabellen.

-- Eine Zeile entspricht einem Jahr (Schweiz gesamt).
CREATE TABLE yearly_stats (
  year               SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
  marriages_total    INT UNSIGNED NOT NULL,
  marriages_per_1000 DECIMAL(4,1) NOT NULL,
  avg_age_m          DECIMAL(4,1) NOT NULL,  -- Durchschnittsalter Männer bei Heirat
  avg_age_f          DECIMAL(4,1) NOT NULL,  -- Durchschnittsalter Frauen bei Heirat
  divorces_total     INT UNSIGNED NOT NULL,
  divorces_per_1000  DECIMAL(4,1) NOT NULL,
  avg_duration       DECIMAL(4,1) NOT NULL   -- Ehedauer bis zur Scheidung in Jahren
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Transform protokolliert, wie viele Zeilen gelesen, gefiltert oder
-- korrigiert wurden. So kann unload.php dieselben Angaben wie die
-- Fallback-Datei liefern.
CREATE TABLE etl_audit (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  metric VARCHAR(80) NOT NULL UNIQUE,
  value  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
