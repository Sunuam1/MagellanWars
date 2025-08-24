-- MagellanWars Database Schema
-- Execute this in Railway MySQL Query tab

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS railway;
USE railway;

-- Table 1: cluster
CREATE TABLE IF NOT EXISTS cluster (
    id smallint(6) DEFAULT '0' NOT NULL,
    name char(50) NOT NULL,
    name_number int(8) DEFAULT '0' NOT NULL,
    PRIMARY KEY (id)
);

-- Table 2: player_pref
CREATE TABLE IF NOT EXISTS player_pref (
    player_id int(11) NOT NULL DEFAULT '0',
    java_choice int(11) NOT NULL DEFAULT '0',
    accept_ally int(11) NOT NULL DEFAULT '-1',
    accept_truce int(11) NOT NULL DEFAULT '-1',
    accept_pact int(11) NOT NULL DEFAULT '-1',
    commander_view int(11) UNSIGNED NOT NULL DEFAULT '15',
    PRIMARY KEY (player_id)
);

-- Table 3: player
CREATE TABLE IF NOT EXISTS player (
    game_id int(10) NOT NULL,
    portal_id int(10) NOT NULL,
    name char(30) NOT NULL,
    home_cluster_id int(10) DEFAULT '0' NOT NULL,
    last_login int(10) DEFAULT '0' NOT NULL,
    last_login_ip varchar(15) DEFAULT '000.000.000.000' NOT NULL,
    mode tinyint(1) DEFAULT '0' NOT NULL,
    race tinyint(2) DEFAULT '0' NOT NULL,
    honor int(3) DEFAULT '50' NOT NULL,
    research_invest int(10) DEFAULT '0' NOT NULL,
    tick int(10) DEFAULT '0' NOT NULL,
    turn int(10) DEFAULT '0' NOT NULL,
    production int(10) DEFAULT '0' NOT NULL,
    ship_production int(10) DEFAULT '0' NOT NULL,
    invested_ship_production int(10) DEFAULT '0' NOT NULL,
    research int(10) DEFAULT '0' NOT NULL,
    ability char(65) DEFAULT '',
    research_tech int(10) DEFAULT '0' NOT NULL,
    admiral_timer int(10) DEFAULT '0' NOT NULL,
    last_turn_production int(10) DEFAULT '0' NOT NULL,
    last_turn_research int(10) DEFAULT '0' NOT NULL,
    last_turn_military int(10) DEFAULT '0' NOT NULL,
    council_id smallint(6) DEFAULT '0' NOT NULL,
    council_vote smallint(6) DEFAULT '0' NOT NULL,
    council_production int(10) DEFAULT '0' NOT NULL,
    council_donation int(10) DEFAULT '0' NOT NULL,
    security_level tinyint(5) DEFAULT '1' NOT NULL,
    alertness int(11) DEFAULT '0' NOT NULL,
    empire_relation int(6) DEFAULT '50' NOT NULL,
    protected_mode tinyint(4) DEFAULT '0' NOT NULL,
    protected_terminate_time int(10) DEFAULT '0' NOT NULL,
    news_turn int(10) DEFAULT '0' NOT NULL,
    news_production int(10) DEFAULT '0' NOT NULL,
    news_research int(10) DEFAULT '0' NOT NULL,
    news_population int(10) DEFAULT '0' NOT NULL,
    news_ability char(65) DEFAULT '',
    news_tech text,
    news_planet text,
    news_project text,
    news_admiral text,
    news_time_news longtext,
    planet_invest_pool int(10) DEFAULT '0' NOT NULL,
    admission_time_limit int(11) DEFAULT '-1' NOT NULL,
    honor_timer int(10) DEFAULT '0' NOT NULL,
    rating int(10) DEFAULT '2000' NOT NULL,
    PRIMARY KEY (game_id),
    KEY idx0 (name),
    UNIQUE idx1 (portal_id)
);

-- Table 4: planet
CREATE TABLE IF NOT EXISTS planet (
    id int(8) NOT NULL,
    cluster int(8) NOT NULL,
    owner int(8) NOT NULL,
    order_ int(8) NOT NULL,
    name char(50) DEFAULT '' NOT NULL,
    attribute char(8) DEFAULT '',
    population int(10) DEFAULT '0' NOT NULL,
    building_factory smallint(5) DEFAULT '0' NOT NULL,
    building_military_base smallint(5) DEFAULT '0' NOT NULL,
    building_research_lab smallint(5) DEFAULT '0' NOT NULL,
    progress_factory smallint(5) DEFAULT '0' NOT NULL,
    progress_military_base smallint(5) DEFAULT '0' NOT NULL,
    progress_research_lab smallint(5) DEFAULT '0' NOT NULL,
    ratio_factory smallint(5) DEFAULT '40' NOT NULL,
    ratio_military_base smallint(5) DEFAULT '30' NOT NULL,
    ratio_research_lab smallint(5) DEFAULT '30' NOT NULL,
    atmosphere char(8) DEFAULT '' NOT NULL,
    temperature smallint(3) DEFAULT '300' NOT NULL,
    size tinyint(1) DEFAULT '2' NOT NULL,
    resource tinyint(1) DEFAULT '2' NOT NULL,
    gravity double DEFAULT '1.0' NOT NULL,
    investment int(10) DEFAULT '0' NOT NULL,
    terraforming smallint(1) DEFAULT '0' NOT NULL,
    terraforming_timer int(10) DEFAULT '0' NOT NULL,
    commerce_with_1 int(10) DEFAULT '0' NOT NULL,
    commerce_with_2 int(10) DEFAULT '0' NOT NULL,
    commerce_with_3 int(10) DEFAULT '0' NOT NULL,
    privateer_timer int(10) DEFAULT '0' NOT NULL,
    blockade_timer int(10) DEFAULT '0' NOT NULL,
    news_population int(10) DEFAULT '0' NOT NULL,
    news_factory smallint(5) DEFAULT '0' NOT NULL,
    news_military_base smallint(5) DEFAULT '0' NOT NULL,
    news_research_lab smallint(5) DEFAULT '0' NOT NULL,
    turns_till_destruction int(11) DEFAULT '0' NOT NULL,
    planet_invest_pool tinyint(1) DEFAULT '0' NOT NULL,
    PRIMARY KEY (id),
    KEY idx0 (owner)
);

-- Table 5: tech
CREATE TABLE IF NOT EXISTS tech (
    owner smallint(6) DEFAULT '0' NOT NULL,
    info char(10) NOT NULL,
    life char(10) NOT NULL,
    matter char(10) NOT NULL,
    social char(10) NOT NULL,
    upgrade char(10) NOT NULL,
    schematics char(10) NOT NULL,
    amatter char(10) NOT NULL,
    PRIMARY KEY (owner)
);

-- Table 6: project
CREATE TABLE IF NOT EXISTS project (
    owner smallint(6) DEFAULT '0' NOT NULL,
    project_id smallint(5) DEFAULT '0' NOT NULL,
    type smallint(6) DEFAULT '0' NOT NULL,
    PRIMARY KEY (owner, project_id)
);

-- Table 7: admiral
CREATE TABLE IF NOT EXISTS admiral (
    id bigint(10) DEFAULT '0' NOT NULL,
    owner int(10) DEFAULT '-1' NOT NULL,
    race smallint(3) DEFAULT '0' NOT NULL,
    type smallint(3) DEFAULT '0' NOT NULL,
    name char(40) NOT NULL,
    exp int(10) DEFAULT '0' NOT NULL,
    level smallint(3) DEFAULT '0' NOT NULL,
    fleet_number int(10) DEFAULT '0' NOT NULL,
    armada_commanding tinyint NOT NULL,
    fleet_commanding smallint(3) DEFAULT '-10' NOT NULL,
    efficiency smallint(3) DEFAULT '-10' NOT NULL,
    offense smallint(3) DEFAULT '-10' NOT NULL,
    offense_up_level smallint(3) DEFAULT '-10' NOT NULL,
    defense smallint(3) DEFAULT '-10' NOT NULL,
    defense_up_level smallint(3) DEFAULT '-10' NOT NULL,
    maneuver smallint(3) DEFAULT '-10' NOT NULL,
    maneuver_up_level smallint(3) DEFAULT '-10' NOT NULL,
    detection smallint(3) DEFAULT '-10' NOT NULL,
    detection_up_level smallint(3) DEFAULT '-10' NOT NULL,
    commonability smallint(2) DEFAULT '-10' NOT NULL,
    raceability smallint(2) DEFAULT '-10' NOT NULL,
    PRIMARY KEY (id)
);

-- Table 8: council
CREATE TABLE IF NOT EXISTS council (
    id int(10) DEFAULT '0' NOT NULL,
    speaker int(10) DEFAULT '0' NOT NULL,
    name char(40) DEFAULT '' NOT NULL,
    slogan char(255) DEFAULT '' NOT NULL,
    production int(10) DEFAULT '0' NOT NULL,
    honor int(3) DEFAULT '50' NOT NULL,
    auto_assign smallint(1) DEFAULT '1' NOT NULL,
    home_cluster_id int(10) DEFAULT '0' NOT NULL,
    merge_penalty_time int(11) DEFAULT '-1' NOT NULL,
    secondary_speaker int(10) DEFAULT '0' NOT NULL,
    PRIMARY KEY (id)
);

-- Table 9: diplomatic_message
CREATE TABLE IF NOT EXISTS diplomatic_message (
    id INT UNSIGNED DEFAULT '0' NOT NULL,
    type SMALLINT DEFAULT '0' NOT NULL,
    sender INT UNSIGNED DEFAULT '0' NOT NULL,
    receiver INT UNSIGNED DEFAULT '0' NOT NULL,
    time INT UNSIGNED DEFAULT '0' NOT NULL,
    status SMALLINT DEFAULT '0' NOT NULL,
    PRIMARY KEY(id),
    KEY idx0 (receiver)
);

-- Table 10: council_message
CREATE TABLE IF NOT EXISTS council_message (
    id INT UNSIGNED DEFAULT '0' NOT NULL,
    type SMALLINT DEFAULT '0' NOT NULL,
    sender INT UNSIGNED DEFAULT '0' NOT NULL,
    receiver INT UNSIGNED DEFAULT '0' NOT NULL,
    time INT UNSIGNED DEFAULT '0' NOT NULL,
    status SMALLINT DEFAULT '0' NOT NULL,
    PRIMARY KEY(id),
    KEY idx0 (receiver)
);

-- Continue with remaining tables...
-- (Tables 11-44 follow the same pattern)

-- Insert initial data
INSERT INTO game_status (last_game_time) VALUES (UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_game_time=UNIX_TIMESTAMP();
INSERT INTO empire (current_outer_planets, current_inner_planets) VALUES (100, 50) ON DUPLICATE KEY UPDATE current_outer_planets=100;