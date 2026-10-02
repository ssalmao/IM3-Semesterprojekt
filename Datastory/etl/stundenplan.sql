-- Datenmodell für die Stundenplan-Story.
--
-- Einmal in phpMyAdmin ausführen. Danach füllt etl/load.php die Tabellen.

-- Die vier Semester stehen je einmal in einer eigenen Tabelle.
CREATE TABLE semesters (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(4) NOT NULL UNIQUE,
  name       VARCHAR(40) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eine Zeile entspricht einem eindeutigen Zeitfenster der Klasse mmp25c2.
CREATE TABLE schedule_slots (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  semester_id    INT NOT NULL,
  class_code     VARCHAR(30) NOT NULL,
  course_name    VARCHAR(255) NOT NULL,
  starts_at      DATETIME NOT NULL,
  duration_hours DECIMAL(5,2) NOT NULL,
  weekday        TINYINT UNSIGNED NOT NULL,
  mode           VARCHAR(20) NOT NULL,
  room           VARCHAR(255),
  UNIQUE KEY unique_slot (semester_id, class_code, starts_at),
  FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Transform protokolliert, wie viele Zeilen gefiltert oder korrigiert
-- wurden. So kann unload.php dieselben Angaben wie die Fallback-Datei liefern.
CREATE TABLE etl_audit (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  metric VARCHAR(80) NOT NULL UNIQUE,
  value  INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
