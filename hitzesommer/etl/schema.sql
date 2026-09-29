-- Das Datenmodell für die Hitzesommer-Daten.
--
-- Anlegen in phpMyAdmin: diesen Text in den Reiter «SQL» einfügen und
-- ausführen. Oder dieselben Spalten im Reiter «Struktur» zusammenklicken –
-- beides führt zum selben Ergebnis.
--
-- Angelegt wird die Struktur genau einmal von Hand. Gefüllt wird sie danach
-- nur noch von load.php.

-- Die drei Städte stehen einmal in einer eigenen Tabelle.
-- Ohne sie stünde «Bern» 86-mal in der grossen Tabelle, und ein einziger
-- Tippfehler würde eine vierte Stadt erfinden, die es nicht gibt.
--
-- UNIQUE sorgt dafür, dass jeder Name höchstens einmal vorkommt.

CREATE TABLE cities (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
);

-- Eine Zeile = eine Stadt in einem vollständigen Sommer.
-- Das ist genau die Untersuchungseinheit aus dem Transform.
--
-- city_id ist der Fremdschlüssel: Er verweist auf cities.id. Die Datenbank
-- lässt deshalb keine Zeile mit einer Stadt zu, die es nicht gibt.
-- SMALLINT reicht für Jahreszahlen und Zähler bis 32767.
-- DECIMAL(4,1) heisst: vier Ziffern, davon eine nach dem Komma – also 36.3.

CREATE TABLE heat_summers (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  city_id           INT NOT NULL,
  year              SMALLINT NOT NULL,
  measurement_days  SMALLINT NOT NULL,
  hot_days          SMALLINT NOT NULL,
  max_temperature_c DECIMAL(4,1),
  FOREIGN KEY (city_id) REFERENCES cities(id)
);
