CREATE TABLE sites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
    nome VARCHAR(255) NOT NULL,
    url VARCHAR(150) NOT NULL,
    visitas INT DEFAULT 1,
    ultima_visita DATETIME,
    inclusao datetime DEFAULT current_timestamp(),
    alteracao datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uk_sites_url (url),
    UNIQUE KEY uk_sites_nome (nome),
    KEY idx_sites_tenant (tenant_id),
    CONSTRAINT fk_sites_tenant FOREIGN KEY (tenant_id) REFERENCES empresas (id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE videos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT(10) UNSIGNED NOT NULL DEFAULT 1,
    nome VARCHAR(255) NOT NULL,
    url VARCHAR(150) NOT NULL,
    visitas INT DEFAULT 1,
    ultima_visita DATETIME,
    inclusao datetime DEFAULT current_timestamp(),
    alteracao datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uk_videos_url (url),
    UNIQUE KEY uk_videos_nome (nome),
    KEY idx_videos_tenant (tenant_id),
    CONSTRAINT fk_videos_tenant FOREIGN KEY (tenant_id) REFERENCES empresas (id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
