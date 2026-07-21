CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla payment.personal_access_tokens: ~6 rows (aproximadamente)
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
	(1, 'App\\Models\\UsuarioApi', 2, 'VivePlus_2022', 'aa2ac9d6f553ada907cc8074a7b1a769b4a29e9ed7c85553142d68ac9bdcd303', '["*"]', '2024-04-09 20:21:00', NULL, '2024-04-09 20:20:39', '2024-04-09 20:21:00'),
	(2, 'App\\Models\\UsuarioApi', 1, 'VivePlus_2022', 'bcc755d6ccbcab1786762b3f0b2d74cd7d0554fcdd33ab1201be19ecf0cfff01', '["*"]', NULL, NULL, '2025-05-02 12:36:18', '2025-05-02 12:36:18'),
	(3, 'App\\Models\\UsuarioApi', 1, 'VivePlus_2022', 'f18bae47a0016791789e522aaaa5270fda24038be65eb9c064104b8315833bae', '["*"]', NULL, NULL, '2025-05-02 13:01:46', '2025-05-02 13:01:46'),
	(4, 'App\\Models\\UsuarioApi', 2, 'VivePlus_2022', 'cfd319977f29b3f12a4737d86cb1e761427bb70266b7de52dc29b0b26895aa6f', '["*"]', NULL, NULL, '2025-08-08 14:36:36', '2025-08-08 14:36:36'),
	(5, 'App\\Models\\UsuarioApi', 2, 'VivePlus_2022', '15ed2ef1ce694d87df40cd555320977cae7eb85f5995b991bc79ed245b195d8d', '["*"]', NULL, NULL, '2026-07-09 00:03:17', '2026-07-09 00:03:17'),
	(6, 'App\\Models\\UsuarioApi', 2, 'VivePlus_2022', 'f07fe065db0e2c478538c3d8765a15bcbfb9e5d3af843ef681c4c5de8458e63d', '["*"]', '2026-07-09 00:07:34', NULL, '2026-07-09 00:03:31', '2026-07-09 00:07:34');


-- Volcando estructura para tabla payment.tbl_rol
CREATE TABLE IF NOT EXISTS `tbl_rol` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla payment.tbl_rol: ~2 rows (aproximadamente)
INSERT INTO `tbl_rol` (`id`, `nombre`) VALUES
	(1, 'user'),
	(2, 'admin');

-- Volcando estructura para tabla payment.tbl_status
CREATE TABLE IF NOT EXISTS `tbl_status` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla payment.tbl_status: ~2 rows (aproximadamente)
INSERT INTO `tbl_status` (`id`, `nombre`) VALUES
	(1, 'activo'),
	(2, 'inactivo');

-- Volcando estructura para tabla payment.tbl_user
CREATE TABLE IF NOT EXISTS `tbl_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `apellido_p` varchar(200) DEFAULT NULL,
  `apellido_m` varchar(200) DEFAULT NULL,
  `email` varchar(200) NOT NULL,
  `telefono` varchar(200) DEFAULT NULL,
  `otp` varchar(200) DEFAULT NULL,
  `password` varchar(200) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete',
  `rol_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_deleted_at_index` (`deleted_at`),
  KEY `fk_user_rol` (`rol_id`),
  KEY `fk_user_status` (`status_id`),
  CONSTRAINT `fk_user_rol` FOREIGN KEY (`rol_id`) REFERENCES `tbl_rol` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_status` FOREIGN KEY (`status_id`) REFERENCES `tbl_status` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tbl_user` (`id`, `nombre`, `apellido_p`, `apellido_m`, `email`, `telefono`, `otp`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `rol_id`, `status_id`) VALUES
	(1, 'Luis', 'ruiz', 'ignacio', 'rickso21111@gmail.com', '5587227800', '0', '123', '$2y$10$6nA0TBpwfpnaWAfp467aOuY4obdr/fBqkIPD7kmF80.OUT/xc41s2', '2026-07-08 23:55:56', '2026-07-08 23:55:56', NULL, 1, 1),
	(2, 'Mario Modificado', 'ceron', 'López', 'mario.nuevo@example.com', '5512345678', '0', '$2y$10$GNfFWwP.65LwkpqQXvFz.eqI.a4pyOcsoZk9SvGtzjp2vy9EKXeke', NULL, '2026-07-08 23:57:04', '2026-07-09 00:13:28', NULL, 1, 1);



CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_index` (`user_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=UTF8MB4_UNICODE_CI;



CREATE TABLE tbl_ubicaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    latitud DECIMAL(10,8) NOT NULL,
    longitud DECIMAL(11,8) NOT NULL,
    precision_metros DECIMAL(10,2) DEFAULT NULL,
    velocidad DECIMAL(10,2) DEFAULT NULL,
    es_activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id)
);


CREATE TABLE tbl_estado_repartidor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    estado VARCHAR(50) NOT NULL, -- disponible, ocupado, desconectado
    mensaje_estado TEXT,
    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id)
);



CREATE TABLE tbl_sesiones_voz (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_origen_id INT NOT NULL,
    usuario_destino_id INT DEFAULT NULL,
    canal VARCHAR(100) NOT NULL,
    tipo ENUM('individual', 'grupal') DEFAULT 'individual',
    activo BOOLEAN DEFAULT TRUE,
    fecha_activacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_desactivacion TIMESTAMP NULL,
    FOREIGN KEY (usuario_origen_id) REFERENCES tbl_user(id),
    FOREIGN KEY (usuario_destino_id) REFERENCES tbl_user(id)
);

CREATE TABLE tbl_alertas_panico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    latitud DECIMAL(10,8) NOT NULL,
    longitud DECIMAL(11,8) NOT NULL,
    tipo_emergencia VARCHAR(50) NOT NULL, -- asaltado, accidente, medico, otro
    descripcion_adicional TEXT,
    estado VARCHAR(20) DEFAULT 'activa', -- activa, desactivada, resuelta
    fecha_activacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_desactivacion TIMESTAMP NULL,
    desactivada_por_usuario_id INT NULL,
    razon_desactivacion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id),
    FOREIGN KEY (desactivada_por_usuario_id) REFERENCES tbl_user(id)
);


CREATE TABLE tbl_respuestas_panico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alerta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    tiempo_estimado VARCHAR(50),
    fecha_respuesta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alerta_id) REFERENCES tbl_alertas_panico(id),
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id)
);

CREATE TABLE tbl_configuracion_panico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL UNIQUE,
    recibir_alertas BOOLEAN DEFAULT TRUE,
    notificacion_push BOOLEAN DEFAULT TRUE,
    notificacion_whatsapp BOOLEAN DEFAULT FALSE,
    notificacion_correo BOOLEAN DEFAULT FALSE,
    radio_alerta_km DECIMAL(10,2) DEFAULT 10.00,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id)
);

CREATE TABLE tbl_notificaciones_push (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    data JSON,
    leido BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id)
);

CREATE TABLE tbl_linea_credito (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    limite_aprobado DECIMAL(10,2) NOT NULL,  -- Ej: 5,000 MXN
     limite_disponible DECIMAL(10,2) NOT NULL, -- Ej: 5,000 MXN
    estatus_id INT DEFAULT 1,                -- Activo, Suspendido, etc.
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id),
    FOREIGN KEY (estatus_id) REFERENCES tbl_status(id)
);

CREATE TABLE tbl_estado_prestamo(
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR (255));

  INSERT INTO tbl_estado_prestamo (`id`, `nombre`) VALUES
	(1, 'solicitado'),
	(2, 'aprobado'),
	(3, 'activo'),
	(4, 'Pagado'),
	(5, 'rechazado');


CREATE TABLE tbl_estado_pago(
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR (255));

  INSERT INTO tbl_estado_pago (`id`, `nombre`) VALUES
	(1, 'pendiente'),
	(2, 'pagado'),
	(3, 'vencido');


	CREATE TABLE tbl_prestamo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folio VARCHAR(20) UNIQUE NOT NULL,
    usuario_id INT NOT NULL,
    linea_credito_id INT NOT NULL,

    -- Datos de la solicitud
    monto_solicitado DECIMAL(10,2) NOT NULL,  -- Lo que el usuario pide
    monto_total_pagar DECIMAL(10,2) NOT NULL, -- Monto + intereses
    numero_pagos INT NOT NULL,                -- Quincenas elegidas
    periodicidad ENUM('quincenal') DEFAULT 'quincenal',

    -- Fechas clave
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion TIMESTAMP NULL,
    fecha_desembolso DATE NULL,
    fecha_primer_pago DATE NULL,

    -- Estados
    estado_prestamo_id INT NOT NULL,          -- Solicitado, Aprobado, Activo, Pagado, Rechazado
    FOREIGN KEY (usuario_id) REFERENCES tbl_user(id),
    FOREIGN KEY (linea_credito_id) REFERENCES tbl_linea_credito(id),
    FOREIGN KEY (estado_prestamo_id) REFERENCES tbl_estado_prestamo(id)
);


CREATE TABLE tbl_amortizacion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prestamo_id INT NOT NULL,
    numero_pago INT NOT NULL,                 -- Quincena 1, 2, 3...
    fecha_vencimiento DATE NOT NULL,
    monto_total DECIMAL(10,2) NOT NULL,       -- Monto a pagar en esa quincena
    estado_pago_id INT NOT NULL,              -- Pendiente, Pagado, Vencido
    fecha_pago DATE NULL,
    referencia_pago VARCHAR(100) NULL,
    FOREIGN KEY (prestamo_id) REFERENCES tbl_prestamo(id),
    FOREIGN KEY (estado_pago_id) REFERENCES tbl_estado_pago(id)
);







