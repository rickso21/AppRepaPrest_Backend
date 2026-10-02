-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         10.4.32-MariaDB - mariadb.org binary distribution
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.13.0.7147
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para siddhite_deliverysobreruedas_qa
CREATE DATABASE IF NOT EXISTS `siddhite_deliverysobreruedas_qa` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `siddhite_deliverysobreruedas_qa`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.activity_logs
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

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.activity_logs: ~0 rows (aproximadamente)
DELETE FROM `activity_logs`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.cache
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.cache: ~0 rows (aproximadamente)
DELETE FROM `cache`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.cache_locks
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.cache_locks: ~0 rows (aproximadamente)
DELETE FROM `cache_locks`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.migrations
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=327392 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.migrations: ~0 rows (aproximadamente)
DELETE FROM `migrations`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.password_reset_tokens: ~0 rows (aproximadamente)
DELETE FROM `password_reset_tokens`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.personal_access_tokens
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
) ENGINE=InnoDB AUTO_INCREMENT=160994890 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.personal_access_tokens: ~3 rows (aproximadamente)
DELETE FROM `personal_access_tokens`;
INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
	(160994887, 'App\\Models\\User', 540013, 'Palabra_Secreta', 'e5a45fdd922ff742ebad6e13a0df6cb29fad27ef9271d4b66f54431bb7ec1be8', '["*"]', '2026-09-25 16:40:13', NULL, '2026-09-25 16:38:11', '2026-09-25 16:40:13'),
	(160994888, 'App\\Models\\User', 540013, 'Palabra_Secreta', '5666b190ac410ff3f9954423d0a5208754fee564de7977f1024df036eb47cde2', '["*"]', NULL, NULL, '2026-09-26 04:17:43', '2026-09-26 04:17:43'),
	(160994889, 'App\\Models\\User', 540013, 'Palabra_Secreta', '30a9c1af412111fbf5c2cc3e8cdd13fc915c2da97e525e61f51ac4f7febb3bb0', '["*"]', '2026-09-26 05:02:26', NULL, '2026-09-26 04:17:54', '2026-09-26 05:02:26');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.sessions
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.sessions: ~0 rows (aproximadamente)
DELETE FROM `sessions`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_alertas_panico
CREATE TABLE IF NOT EXISTS `tbl_alertas_panico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `tipo_emergencia` varchar(50) NOT NULL,
  `descripcion_adicional` text DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'activa',
  `fecha_activacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_desactivacion` timestamp NULL DEFAULT NULL,
  `desactivada_por_usuario_id` int(11) DEFAULT NULL,
  `razon_desactivacion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `desactivada_por_usuario_id` (`desactivada_por_usuario_id`),
  CONSTRAINT `tbl_alertas_panico_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`),
  CONSTRAINT `tbl_alertas_panico_ibfk_2` FOREIGN KEY (`desactivada_por_usuario_id`) REFERENCES `tbl_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=600057 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_alertas_panico: ~0 rows (aproximadamente)
DELETE FROM `tbl_alertas_panico`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_configuracion_panico
CREATE TABLE IF NOT EXISTS `tbl_configuracion_panico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `recibir_alertas` tinyint(1) DEFAULT 1,
  `notificacion_push` tinyint(1) DEFAULT 1,
  `notificacion_whatsapp` tinyint(1) DEFAULT 0,
  `notificacion_correo` tinyint(1) DEFAULT 0,
  `radio_alerta_km` decimal(10,2) DEFAULT 10.00,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tbl_configuracion_panico_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=540007 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_configuracion_panico: ~2 rows (aproximadamente)
DELETE FROM `tbl_configuracion_panico`;
INSERT INTO `tbl_configuracion_panico` (`id`, `usuario_id`, `recibir_alertas`, `notificacion_push`, `notificacion_whatsapp`, `notificacion_correo`, `radio_alerta_km`, `fecha_actualizacion`) VALUES
	(540005, 540012, 1, 1, 0, 0, 10.00, '2026-09-25 16:35:43'),
	(540006, 540013, 1, 1, 0, 0, 10.00, '2026-09-25 16:37:37');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_estado_pago
CREATE TABLE IF NOT EXISTS `tbl_estado_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_estado_pago: ~3 rows (aproximadamente)
DELETE FROM `tbl_estado_pago`;
INSERT INTO `tbl_estado_pago` (`id`, `nombre`) VALUES
	(1, 'pendiente'),
	(2, 'pagado'),
	(3, 'vencido');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_estado_prestamo
CREATE TABLE IF NOT EXISTS `tbl_estado_prestamo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30006 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_estado_prestamo: ~5 rows (aproximadamente)
DELETE FROM `tbl_estado_prestamo`;
INSERT INTO `tbl_estado_prestamo` (`id`, `nombre`) VALUES
	(1, 'solicitado'),
	(2, 'aprobado'),
	(3, 'activo'),
	(4, 'Pagado'),
	(5, 'rechazado');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_estado_repartidor
CREATE TABLE IF NOT EXISTS `tbl_estado_repartidor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `estado` varchar(50) NOT NULL,
  `mensaje_estado` text DEFAULT NULL,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tbl_estado_repartidor_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=540007 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_estado_repartidor: ~2 rows (aproximadamente)
DELETE FROM `tbl_estado_repartidor`;
INSERT INTO `tbl_estado_repartidor` (`id`, `usuario_id`, `estado`, `mensaje_estado`, `ultima_actualizacion`) VALUES
	(540005, 540012, 'desconectado', 'Usuario recién registrado', '2026-09-25 16:35:43'),
	(540006, 540013, 'conectado', 'Activo', '2026-09-26 05:02:27');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_group
CREATE TABLE IF NOT EXISTS `tbl_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `user_leader_id` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=300004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_group: ~1 rows (aproximadamente)
DELETE FROM `tbl_group`;
INSERT INTO `tbl_group` (`id`, `group_name`, `code`, `user_leader_id`, `status`) VALUES
	(300003, 'delivery', 'OYENVFZH', 540012, 1);

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_linea_credito
CREATE TABLE IF NOT EXISTS `tbl_linea_credito` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `limite_aprobado` decimal(10,2) NOT NULL,
  `limite_disponible` decimal(10,2) NOT NULL,
  `estatus_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `estatus_id` (`estatus_id`),
  CONSTRAINT `tbl_linea_credito_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`),
  CONSTRAINT `tbl_linea_credito_ibfk_2` FOREIGN KEY (`estatus_id`) REFERENCES `tbl_status` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=420013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_linea_credito: ~0 rows (aproximadamente)
DELETE FROM `tbl_linea_credito`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_pagos
CREATE TABLE IF NOT EXISTS `tbl_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prestamo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `monto_restante` decimal(10,2) NOT NULL,
  `saldo_antes_pago` decimal(10,2) DEFAULT NULL,
  `tipo_pago` enum('normal','anticipado','completo') DEFAULT 'normal',
  `es_adelantado` tinyint(1) NOT NULL DEFAULT 0,
  `numero_quincena` int(11) DEFAULT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT current_timestamp(),
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta','otro') DEFAULT 'efectivo',
  `usuario_registro` int(11) DEFAULT NULL,
  `estado_pago` enum('pendiente','confirmado','rechazado','cancelado') NOT NULL DEFAULT 'confirmado',
  `fecha_confirmacion` timestamp NULL DEFAULT NULL,
  `fecha_desembolso_original` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0: Pendiente, 1: Aprobado, 2: Rechazado',
  `preference_id` varchar(255) DEFAULT NULL,
  `no_pago_mp` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `fecha_verificacion` timestamp NULL DEFAULT NULL,
  `usuario_confirmo` bigint(20) unsigned DEFAULT NULL,
  `status_mp` int(11) NOT NULL DEFAULT 1 COMMENT '0: Pendiente, 1: Aprobado, 2: Rechazado',
  `clabe_interbancaria` varchar(255) DEFAULT NULL,
  `numero_cuenta` varchar(255) DEFAULT NULL,
  `banco_emisor` varchar(255) DEFAULT NULL,
  `nombre_beneficiario` varchar(255) DEFAULT NULL,
  `referencia_banco` varchar(255) DEFAULT NULL,
  `fecha_expiracion` timestamp NULL DEFAULT NULL,
  `comprobante_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prestamo_id` (`prestamo_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_pagos_prestamo_tipo` (`prestamo_id`,`tipo_pago`),
  KEY `idx_pagos_fecha_pago` (`fecha_pago`),
  KEY `idx_pagos_adelantado` (`es_adelantado`),
  KEY `idx_pagos_estado` (`estado_pago`),
  CONSTRAINT `tbl_pagos_ibfk_1` FOREIGN KEY (`prestamo_id`) REFERENCES `tbl_prestamo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_pagos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1680001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_pagos: ~0 rows (aproximadamente)
DELETE FROM `tbl_pagos`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_prestamo
CREATE TABLE IF NOT EXISTS `tbl_prestamo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `linea_credito_id` int(11) NOT NULL,
  `monto_solicitado` decimal(10,2) NOT NULL,
  `monto_total_pagar` decimal(10,2) NOT NULL,
  `monto_restante` decimal(10,2) DEFAULT NULL,
  `numero_pagos` int(11) NOT NULL,
  `pagos_realizados` int(11) NOT NULL DEFAULT 0,
  `pago_quincenal` decimal(10,2) DEFAULT NULL,
  `periodicidad` enum('quincenal') DEFAULT 'quincenal',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `fecha_desembolso` date DEFAULT NULL,
  `fecha_activacion` timestamp NULL DEFAULT NULL,
  `fecha_liquidacion` timestamp NULL DEFAULT NULL,
  `fecha_primer_pago` date DEFAULT NULL,
  `fecha_ultimo_pago` timestamp NULL DEFAULT NULL,
  `fecha_fin` timestamp NULL DEFAULT NULL,
  `estado_prestamo_id` int(11) NOT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `incremento_aplicado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_incremento` timestamp NULL DEFAULT NULL,
  `ruta_pdf_aprobacion` varchar(255) DEFAULT NULL,
  `ruta_pdf_liquidacion` varchar(255) DEFAULT NULL,
  `ruta_pdf_rechazo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `usuario_id` (`usuario_id`),
  KEY `linea_credito_id` (`linea_credito_id`),
  KEY `estado_prestamo_id` (`estado_prestamo_id`),
  CONSTRAINT `tbl_prestamo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`),
  CONSTRAINT `tbl_prestamo_ibfk_2` FOREIGN KEY (`linea_credito_id`) REFERENCES `tbl_linea_credito` (`id`),
  CONSTRAINT `tbl_prestamo_ibfk_3` FOREIGN KEY (`estado_prestamo_id`) REFERENCES `tbl_estado_prestamo` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1080002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_prestamo: ~0 rows (aproximadamente)
DELETE FROM `tbl_prestamo`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_respuestas_panico
CREATE TABLE IF NOT EXISTS `tbl_respuestas_panico` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alerta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `tiempo_estimado` varchar(50) DEFAULT NULL,
  `fecha_respuesta` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `alerta_id` (`alerta_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tbl_respuestas_panico_ibfk_1` FOREIGN KEY (`alerta_id`) REFERENCES `tbl_alertas_panico` (`id`),
  CONSTRAINT `tbl_respuestas_panico_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_respuestas_panico: ~0 rows (aproximadamente)
DELETE FROM `tbl_respuestas_panico`;

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_rol
CREATE TABLE IF NOT EXISTS `tbl_rol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_rol: ~3 rows (aproximadamente)
DELETE FROM `tbl_rol`;
INSERT INTO `tbl_rol` (`id`, `nombre`) VALUES
	(1, 'user'),
	(2, 'admin'),
	(3, 'super admin');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_status
CREATE TABLE IF NOT EXISTS `tbl_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_status: ~2 rows (aproximadamente)
DELETE FROM `tbl_status`;
INSERT INTO `tbl_status` (`id`, `nombre`) VALUES
	(1, 'activo'),
	(2, 'inactivo');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_ubicaciones
CREATE TABLE IF NOT EXISTS `tbl_ubicaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `precision_metros` decimal(10,2) DEFAULT NULL,
  `velocidad` decimal(10,2) DEFAULT NULL,
  `es_activa` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tbl_ubicaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `tbl_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=125578 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_ubicaciones: ~1 rows (aproximadamente)
DELETE FROM `tbl_ubicaciones`;
INSERT INTO `tbl_ubicaciones` (`id`, `usuario_id`, `latitud`, `longitud`, `precision_metros`, `velocidad`, `es_activa`, `created_at`) VALUES
	(125577, 540013, 37.42199830, -122.08400000, NULL, NULL, 1, '2026-09-25 22:23:16');

-- Volcando estructura para tabla siddhite_deliverysobreruedas_qa.tbl_user
CREATE TABLE IF NOT EXISTS `tbl_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `apellido_p` varchar(200) DEFAULT NULL,
  `apellido_m` varchar(200) DEFAULT NULL,
  `email` varchar(200) NOT NULL,
  `telefono` varchar(200) DEFAULT NULL,
  `otp` varchar(200) DEFAULT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `password` varchar(200) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `token_pc` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete',
  `rol_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `en_llamada` tinyint(1) DEFAULT 0 COMMENT 'Indica si el usuario está en una llamada activa',
  `llamada_de` bigint(20) unsigned DEFAULT NULL COMMENT 'ID del usuario que inició la llamada',
  `current_channel` varchar(255) DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_deleted_at_index` (`deleted_at`),
  KEY `fk_user_rol` (`rol_id`),
  KEY `fk_user_status` (`status_id`),
  KEY `idx_users_en_llamada` (`en_llamada`),
  CONSTRAINT `fk_user_rol` FOREIGN KEY (`rol_id`) REFERENCES `tbl_rol` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_status` FOREIGN KEY (`status_id`) REFERENCES `tbl_status` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=540014 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Volcando datos para la tabla siddhite_deliverysobreruedas_qa.tbl_user: ~2 rows (aproximadamente)
DELETE FROM `tbl_user`;
INSERT INTO `tbl_user` (`id`, `nombre`, `apellido_p`, `apellido_m`, `email`, `telefono`, `otp`, `grupo_id`, `password`, `remember_token`, `token_pc`, `created_at`, `updated_at`, `deleted_at`, `rol_id`, `status_id`, `en_llamada`, `llamada_de`, `current_channel`) VALUES
	(540012, 'luis', 'Principal', '0', 'fd@gmail.com', '5555555555', NULL, 300003, '$2y$12$O1ADyNfu55l/.YVh4IazpuwhDDT30mAsaZ18HMZPNb0NuBqGoz5I2', NULL, NULL, '2026-09-25 16:35:43', '2026-09-25 16:35:43', NULL, 2, 1, 0, NULL, 'general'),
	(540013, 'Luis', 'Ruiz', 'ignacio', 'rickso21113@gmail.com', '1111111111', NULL, 300003, '$2y$12$GYrVvHyeG3fKrF2dulOkWuYUnAd30zd.EC9Rs2TCX4N5gFK3JeUS2', NULL, NULL, '2026-09-25 16:37:37', '2026-09-25 16:37:37', NULL, 1, 1, 0, NULL, 'general');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
