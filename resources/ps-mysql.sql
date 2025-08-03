-- #! sqlite
-- # { data
-- #     { setup
CREATE TABLE IF NOT EXISTS playerdata(name VARCHAR(255) PRIMARY KEY, kills INTEGER, deaths INTEGER)
-- #     }

-- #     { addplayer
-- #     :name string
-- #     :kills int
-- #     :deaths int
INSERT INTO playerdata(name, kills, deaths) VALUES(:name, :kills, :deaths)
-- #     }

-- #     { getdata
-- #     :name string
SELECT * FROM playerdata WHERE name = :name
-- #     }

-- #     { setkills
-- #     :name string
-- #     :kills int
UPDATE playerdata SET kills = :kills WHERE name = :name
-- #     }

-- #     { setdeaths
-- #     :name string
-- #     :deaths int
UPDATE playerdata SET deaths = :deaths WHERE name = :name
-- #     }
-- # }