-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 02-06-2026 a las 13:37:11
-- Versión del servidor: 8.0.45-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `vinomadrid_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas_admin`
--

CREATE TABLE `alertas_admin` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nombre_usuario` varchar(100) DEFAULT NULL,
  `motivo` varchar(100) NOT NULL,
  `simbolo` varchar(10) DEFAULT '!',
  `reconocida` tinyint(1) DEFAULT '0',
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `alertas_admin`
--

INSERT INTO `alertas_admin` (`id`, `user_id`, `nombre_usuario`, `motivo`, `simbolo`, `reconocida`, `fecha`) VALUES
(1, 1, 'Usuario de Prueba', 'Solicitud presupuesto', '!', 0, '2026-05-09 21:36:50'),
(24, 24, 'marsell', 'Suscripción dominio masell', '❔', 1, '2026-05-11 09:40:02'),
(25, 25, 'Tipico', 'Suscripción dominio tipico', '❔', 0, '2026-05-11 09:40:02'),
(28, 29, 'Skincare', 'Suscripción dominio skincare', '❔', 1, '2026-05-11 09:40:02'),
(29, 30, 'Mariano Franco', 'Solicitud presupuesto', '!', 1, '2026-05-11 11:08:53'),
(30, 30, 'Mariano Franco', 'Suscripción dominio marianofranco', '❔', 1, '2026-05-11 11:09:02'),
(31, 31, 'Adrian martin', 'Solicitud presupuesto', '!', 1, '2026-05-12 11:42:33'),
(68, 1, 'Elizabeth', 'Gestión Dominio: comprar_dominio', '❓', 0, '2026-05-19 17:29:51'),
(69, 1, 'Elizabeth', 'Gestión Dominio: desvincular_dominio', '❓', 0, '2026-05-19 17:30:40'),
(70, 1, 'Elizabeth', 'Modificación módulos', '❓', 0, '2026-05-22 13:51:52'),
(83, 31, 'Adrian martin', 'Solicitud borrado cuenta', '⚠️', 1, '2026-05-26 18:08:25'),
(101, 24, 'marsell', 'Gestión Dominio: cambiar_subdominio', '❓', 0, '2026-05-26 19:07:06'),
(102, 24, 'marsell', 'Dominio agregado o actualizado: marsell', 'DOM', 0, '2026-05-26 19:08:02'),
(137, 31, 'Adrian martin', 'Proyecto de Diseño Web aprobado y facturado. Garantia de 30 dias activa.', 'WEB', 0, '2026-05-28 18:08:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dominios`
--

CREATE TABLE `dominios` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `subdominio_alias` varchar(100) DEFAULT NULL,
  `dominio_propio` varchar(255) DEFAULT NULL,
  `estado_dominio` varchar(50) DEFAULT 'Pendiente',
  `fecha_caducidad` date DEFAULT NULL,
  `renovacion_auto` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `dominios`
--

INSERT INTO `dominios` (`id`, `user_id`, `subdominio_alias`, `dominio_propio`, `estado_dominio`, `fecha_caducidad`, `renovacion_auto`) VALUES
(27, 24, 'marsell', NULL, 'Activo', '2027-05-09', 1),
(29, 25, 'tipico', NULL, 'Activo', NULL, 0),
(34, 29, 'skincare', NULL, 'Activo', NULL, 0),
(35, 30, 'marianofranco', 'mariano.es', 'Activo', '2027-05-11', 1),
(36, 31, 'adrian', 'adrian.es', 'Activo', '2027-05-12', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id` int NOT NULL,
  `tipo` enum('factura','reembolso','rectificativa') NOT NULL DEFAULT 'factura',
  `user_id` int NOT NULL,
  `fecha_emision` date NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `base_imponible` decimal(10,2) NOT NULL DEFAULT '0.00',
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT '21.00',
  `iva_importe` decimal(10,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(10,2) NOT NULL,
  `estado` enum('Pagado','Pendiente','Cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Pendiente',
  `detalles_json` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `facturas`
--

INSERT INTO `facturas` (`id`, `tipo`, `user_id`, `fecha_emision`, `concepto`, `base_imponible`, `iva_porcentaje`, `iva_importe`, `importe`, `estado`, `detalles_json`) VALUES
(1, 'factura', 1, '2026-04-15', 'Plan BÁSICO - Abril', 0.00, 21.00, 0.00, 4.99, 'Pagado', NULL),
(2, 'factura', 1, '2026-05-15', 'Plan BÁSICO - Mayo', 0.00, 21.00, 0.00, 4.99, 'Pagado', NULL),
(19, 'factura', 31, '2026-05-28', 'Desarrollo de Sitio Web Personalizado', 101.65, 21.00, 21.35, 123.00, 'Pagado', '[{\"item\": \"Proyecto Desarrollo Web Llave en Mano\", \"precio\": 123}]'),
(28, 'reembolso', 31, '2026-05-28', 'Reembolso por desistimiento - Garantía 30 días (Desarrollo Web)', -101.65, 21.00, -21.35, -123.00, 'Pagado', '[{\"item\": \"Abono e inversion de propuesta de Diseno Web\", \"precio\": -123, \"proyecto_id\": 1, \"factura_original_id\": 19}]');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ftp_cuentas_extra`
--

CREATE TABLE `ftp_cuentas_extra` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `ftp_user` varchar(50) NOT NULL,
  `ftp_pass` varchar(255) NOT NULL,
  `estado` enum('Pendiente','Tramitando','Activo','Error','Para_Borrar','Para_Modificar') DEFAULT 'Pendiente',
  `owner_ftp` varchar(100) DEFAULT NULL,
  `creado_en_so` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ftp_cuentas_extra`
--

INSERT INTO `ftp_cuentas_extra` (`id`, `user_id`, `ftp_user`, `ftp_pass`, `estado`, `owner_ftp`, `creado_en_so`) VALUES
(10, 30, 'u30_ventas', '12345678', 'Activo', 'marianofranco', 0),
(11, 31, 'u31_marketing', '12345678', 'Activo', 'adrianmartin', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_chat`
--

CREATE TABLE `mensajes_chat` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `emisor` enum('admin','usuario') NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('texto','archivo','comando') DEFAULT 'texto',
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  `leido` tinyint(1) DEFAULT '0',
  `modulo_ia` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `mensajes_chat`
--

INSERT INTO `mensajes_chat` (`id`, `user_id`, `emisor`, `mensaje`, `tipo`, `fecha`, `leido`, `modulo_ia`) VALUES
(1, 1, 'admin', 'hola', 'texto', '2026-05-10 01:42:04', 0, 0),
(5, 30, 'usuario', 'Prueba de que funciona el chat y el log de los chat de q hay un mensaje activo, (solo logs)', 'texto', '2026-05-11 13:10:39', 0, 0),
(6, 1, 'admin', 'a', 'texto', '2026-05-12 22:55:15', 0, 0),
(7, 31, 'admin', 'Buenas Buenas', 'texto', '2026-05-12 23:15:42', 0, 0),
(8, 31, 'usuario', 'Que pasaaaaaaaa', 'texto', '2026-05-12 23:16:10', 0, 0),
(9, 31, 'usuario', 'Quiero una pagina web con ia', 'texto', '2026-05-12 23:20:49', 0, 0),
(10, 31, 'admin', 'Perfecto, como le gustaria la página?', 'texto', '2026-05-12 23:35:42', 0, 0),
(18, 1, 'admin', 'hola', 'texto', '2026-05-14 17:45:34', 0, 0),
(19, 1, 'admin', 'adios', 'texto', '2026-05-14 17:49:38', 0, 0),
(23, 1, 'admin', 'hola', 'texto', '2026-05-20 18:12:40', 0, 0),
(53, 31, 'admin', 'hola', 'texto', '2026-05-28 20:02:20', 0, 0),
(54, 31, 'admin', 'asd', 'texto', '2026-05-28 20:02:28', 0, 0),
(55, 31, 'admin', 'hola', 'texto', '2026-05-28 20:02:35', 0, 0),
(56, 31, 'admin', 'Presupuesto aprobado. Precio final del proyecto web: 123,00 EUR. Ya puedes consultar la factura desde tu panel y dispones de 30 dias de garantia para solicitar el reembolso si procede.', 'texto', '2026-05-28 20:08:33', 0, 0),
(90, 31, 'admin', 'El desistimiento se ha procesado correctamente. Se ha emitido la factura de reembolso y su acceso al módulo ha sido revocado. Podrá volver a acceder si adquiere el módulo nuevamente', 'texto', '2026-05-28 22:32:04', 0, 0),
(96, 1, 'admin', 'ghjgjh', 'texto', '2026-06-01 22:36:54', 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulo_mysql`
--

CREATE TABLE `modulo_mysql` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `db_user` varchar(64) NOT NULL,
  `db_pass` varchar(255) NOT NULL,
  `estado` enum('Pendiente','Activo','Para_Borrar') DEFAULT 'Pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `modulo_mysql`
--

INSERT INTO `modulo_mysql` (`id`, `user_id`, `db_name`, `db_user`, `db_pass`, `estado`) VALUES
(1, 1, 'u1_eli_db', 'u1_eli', 'Control_Z88$', 'Activo'),
(2, 24, 'u24_marsell', 'u24_marsell', '12345678', 'Activo'),
(3, 30, 'u30_mariano_db', 'u30_mariano_db', '12345678', 'Activo'),
(4, 31, 'u31_adrian', 'u31_adrian', '12345678', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyectos_diseno_web`
--

CREATE TABLE `proyectos_diseno_web` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `estado` enum('tramitando_propuesta','activo','reembolsado') NOT NULL DEFAULT 'tramitando_propuesta',
  `precio_final` decimal(10,2) DEFAULT '0.00',
  `fecha_pago` datetime DEFAULT NULL,
  `fecha_garantia_expira` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `proyectos_diseno_web`
--

INSERT INTO `proyectos_diseno_web` (`id`, `user_id`, `estado`, `precio_final`, `fecha_pago`, `fecha_garantia_expira`) VALUES
(1, 31, 'reembolsado', 123.00, '2026-05-28 20:08:33', '2026-06-27 20:08:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_plain` varchar(255) DEFAULT NULL,
  `plan_contratado` varchar(50) DEFAULT 'Ninguno',
  `estado_servicio` varchar(50) DEFAULT 'Pendiente',
  `ftp_user` varchar(50) DEFAULT NULL,
  `ftp_pass` varchar(50) DEFAULT NULL,
  `storage_qty` int NOT NULL DEFAULT '0',
  `extras_json` text,
  `rol` varchar(20) DEFAULT 'usuario',
  `fecha_cancelacion` datetime DEFAULT NULL,
  `creado_en_so` tinyint(1) DEFAULT '0',
  `showcase_permission` tinyint(1) NOT NULL DEFAULT '1',
  `multiuser_qty` int NOT NULL DEFAULT '0',
  `modulo_ia` tinyint(1) DEFAULT '0',
  `fecha_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  `renovacion_automatica` tinyint(1) DEFAULT '1',
  `nombre_fiscal` varchar(150) DEFAULT NULL,
  `documento_identidad` varchar(50) DEFAULT NULL,
  `direccion_completa` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT '0'
) ;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `password_plain`, `plan_contratado`, `estado_servicio`, `ftp_user`, `ftp_pass`, `storage_qty`, `extras_json`, `rol`, `fecha_cancelacion`, `creado_en_so`, `showcase_permission`, `multiuser_qty`, `modulo_ia`, `fecha_alta`, `renovacion_automatica`, `nombre_fiscal`, `documento_identidad`, `direccion_completa`, `email_verificado`) VALUES
(1, 'Elizabeth', 'elirosellbonavina@gmail.com', '$2y$10$gZ.gjAnwV35n4s3T5Zbff.5m6flMjSVlqMRH3Vb0nk9t8vwA/WIvW', '12345678', 'PROFESIONAL', 'Activo', 'elizabeth', '12345678', 0, '[]', 'admin', NULL, 1, 0, 0, 0, '2026-05-22 11:31:58', 1, NULL, NULL, NULL, 0),
(24, 'marsell', 'marsell@gmail.com', '$2y$10$1FzTeTooNR/soiL1PKAvH.F9mDNp./iNtqX1n7ePXiykvvpEFIUmC', '12345678', 'Básico', 'Activo', 'marsell', '12345678', 0, '[\"sql_php|5.00\",\"domain|15.00\"]', 'usuario', NULL, 1, 1, 0, 0, '2026-05-22 11:31:58', 1, NULL, NULL, NULL, 0),
(25, 'Tipico', 'tipico@gmail.com', '$2y$10$zTBTkDy6Bc7tgiM42V5B2.60BefR.L/MkqByPPWTBKpWePPLDbET2', NULL, 'BÁSICO', 'Activo', 'tipico', 'pass_48837e', 0, '[]', 'usuario', NULL, 1, 0, 0, 0, '2026-05-22 11:31:58', 1, NULL, NULL, NULL, 0),
(29, 'Skincare', 'Skincare@gmail.com', '$2y$10$5DZstcDJDnN3qpv7YvTrkeCNd9PqSyCS.8ucKejZxwx0KVQ8/eAmK', '12345678', 'BÁSICO', 'Activo', 'skincare', 'pass_b36fc3', 0, '[]', 'usuario', NULL, 1, 0, 0, 0, '2026-05-22 11:31:58', 1, NULL, NULL, NULL, 0),
(30, 'Mariano Franco', 'Mariano@gmail.com', '$2y$10$Tu7Ldh6KayF8LpZ63SaEiOF5cZ9U4s0BE9mO3bzf/KaGnvN/6uMDq', '12345678', 'Básico', 'Activo', 'marianofranco', 'pass_90b56c', 0, '[\"domain|15.00\",\"web_ai|100.00\",\"sql_php|5.00\"]', 'admin', NULL, 1, 0, 1, 1, '2026-05-22 11:31:58', 1, NULL, NULL, NULL, 0),
(31, 'Adrian martin', 'adrian@gmail.com', '$2y$10$N2U.o5ci92p.B1G8kdr1lOjRCDY7GaCGnbjrBPcuQqYAeMoX8e1Ya', '12345678', 'Básico', 'Activo', 'adrianmartin', 'pass_b0414a', 1, '[\"domain|15.00\",\"sql_php|5.00\"]', 'admin', '2026-05-26 20:08:25', 1, 0, 0, 0, '2026-05-22 11:31:58', 0, 'adrian', '12345678a', 'calle ubuntu', 0);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas_admin`
--
ALTER TABLE `alertas_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_usuario_alerta` (`user_id`);

--
-- Indices de la tabla `dominios`
--
ALTER TABLE `dominios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id_dominios` (`user_id`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `ftp_cuentas_extra`
--
ALTER TABLE `ftp_cuentas_extra`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ftp_user` (`ftp_user`),
  ADD KEY `fk_ftp_extra_usuarios` (`user_id`);

--
-- Indices de la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indices de la tabla `modulo_mysql`
--
ALTER TABLE `modulo_mysql`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `uq_user` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indices de la tabla `proyectos_diseno_web`
--
ALTER TABLE `proyectos_diseno_web`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_nombre_usuario` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_admin`
--
ALTER TABLE `alertas_admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT de la tabla `dominios`
--
ALTER TABLE `dominios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de la tabla `ftp_cuentas_extra`
--
ALTER TABLE `ftp_cuentas_extra`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT de la tabla `modulo_mysql`
--
ALTER TABLE `modulo_mysql`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `proyectos_diseno_web`
--
ALTER TABLE `proyectos_diseno_web`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas_admin`
--
ALTER TABLE `alertas_admin`
  ADD CONSTRAINT `fk_usuario_alerta` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `dominios`
--
ALTER TABLE `dominios`
  ADD CONSTRAINT `dominios_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dominios_usuarios` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u_dominios` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ftp_cuentas_extra`
--
ALTER TABLE `ftp_cuentas_extra`
  ADD CONSTRAINT `fk_ftp_extra_usuarios` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u_ftp_extra` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ftp_cuentas_extra_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  ADD CONSTRAINT `fk_chat_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `modulo_mysql`
--
ALTER TABLE `modulo_mysql`
  ADD CONSTRAINT `fk_mysql_usuarios` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_u_mysql` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modulo_mysql_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `proyectos_diseno_web`
--
ALTER TABLE `proyectos_diseno_web`
  ADD CONSTRAINT `proyectos_diseno_web_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
