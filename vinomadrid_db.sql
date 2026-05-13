-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 13-05-2026 a las 00:04:59
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
(4, 18, 'asasa', 'Solicitud presupuesto', '!', 1, '2026-05-09 22:06:26'),
(5, 22, 'staffss', 'Solicitud presupuesto', '!', 1, '2026-05-09 22:06:26'),
(6, 26, 'pag wweb', 'Solicitud presupuesto', '!', 0, '2026-05-09 22:34:52'),
(7, 1, 'Elizabeth', 'Suscripción dominio None', '❔', 1, '2026-05-11 09:40:02'),
(8, 4, 'kisell', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(9, 6, 'Prueba_empresa', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(10, 7, 'Prueba_Python', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(11, 8, 'prueba_crontab', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(12, 9, 'albertoaestadoaqui', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(13, 10, 'albertoaestadoaquidos', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(14, 11, 'Alex', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(15, 12, 'Test', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(16, 13, 'elUsu', 'Suscripción dominio None', '❔', 1, '2026-05-11 09:40:02'),
(17, 15, 'aq', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(18, 16, 'prueba_nombres_ftp', 'Suscripción dominio None', '❔', 0, '2026-05-11 09:40:02'),
(19, 18, 'asasa', 'Suscripción dominio asasa', '❔', 0, '2026-05-11 09:40:02'),
(20, 19, 'prueba nueva', 'Suscripción dominio pruebanueva', '❔', 0, '2026-05-11 09:40:02'),
(21, 20, 'aaasd', 'Suscripción dominio aaasd', '❔', 0, '2026-05-11 09:40:02'),
(22, 21, 'prueba', 'Suscripción dominio prueba', '❔', 0, '2026-05-11 09:40:02'),
(23, 22, 'staffss', 'Suscripción dominio staffss', '❔', 0, '2026-05-11 09:40:02'),
(24, 24, 'marsell', 'Suscripción dominio masell', '❔', 1, '2026-05-11 09:40:02'),
(25, 25, 'Tipico', 'Suscripción dominio tipico', '❔', 0, '2026-05-11 09:40:02'),
(26, 26, 'pag wweb', 'Suscripción dominio user_26', '❔', 1, '2026-05-11 09:40:02'),
(27, 26, 'pag wweb', 'Suscripción dominio pagwweb', '❔', 0, '2026-05-11 09:40:02'),
(28, 29, 'Skincare', 'Suscripción dominio skincare', '❔', 0, '2026-05-11 09:40:02'),
(29, 30, 'Mariano Franco', 'Solicitud presupuesto', '!', 1, '2026-05-11 11:08:53'),
(30, 30, 'Mariano Franco', 'Suscripción dominio marianofranco', '❔', 1, '2026-05-11 11:09:02'),
(31, 31, 'Adrian martin', 'Solicitud presupuesto', '!', 1, '2026-05-12 11:42:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dominios`
--

CREATE TABLE `dominios` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `subdominio_alias` varchar(100) DEFAULT NULL,
  `dominio_propio` varchar(255) DEFAULT NULL,
  `estado_dominio` enum('Pendiente','Activo','Para_Borrar') DEFAULT 'Pendiente',
  `fecha_caducidad` date DEFAULT NULL,
  `renovacion_auto` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `dominios`
--

INSERT INTO `dominios` (`id`, `user_id`, `subdominio_alias`, `dominio_propio`, `estado_dominio`, `fecha_caducidad`, `renovacion_auto`) VALUES
(1, 1, NULL, NULL, 'Activo', NULL, 1),
(2, 15, NULL, NULL, 'Activo', NULL, 1),
(3, 9, NULL, NULL, 'Activo', NULL, 1),
(4, 10, NULL, NULL, 'Activo', NULL, 1),
(5, 11, NULL, NULL, 'Activo', NULL, 1),
(7, 8, NULL, NULL, 'Activo', NULL, 1),
(8, 1, NULL, NULL, 'Activo', NULL, 1),
(9, 6, NULL, NULL, 'Activo', NULL, 1),
(10, 4, NULL, NULL, 'Activo', NULL, 1),
(11, 16, NULL, NULL, 'Activo', NULL, 1),
(12, 7, NULL, NULL, 'Activo', NULL, 1),
(14, 12, NULL, NULL, 'Activo', NULL, 1),
(15, 13, NULL, NULL, 'Activo', NULL, 1),
(18, 18, 'asasa', NULL, 'Activo', '2027-05-08', 1),
(19, 19, 'pruebanueva', NULL, 'Activo', NULL, 0),
(20, 19, 'pruebanueva', NULL, 'Activo', NULL, 0),
(21, 20, 'aaasd', NULL, 'Activo', NULL, 0),
(22, 21, 'prueba', NULL, 'Activo', NULL, 0),
(23, 21, 'prueba', NULL, 'Activo', NULL, 0),
(24, 22, 'staffss', NULL, 'Activo', '2027-05-09', 1),
(27, 24, 'masell', NULL, 'Activo', '2027-05-09', 1),
(28, 25, 'tipico', NULL, 'Activo', NULL, 0),
(29, 25, 'tipico', NULL, 'Activo', NULL, 0),
(30, 26, 'user_26', NULL, 'Activo', '2027-05-10', 1),
(31, 26, 'pagwweb', NULL, 'Activo', '2027-05-10', 1),
(34, 29, 'skincare', NULL, 'Activo', NULL, 0),
(35, 30, 'marianofranco', 'mariano.es', 'Activo', '2027-05-11', 1),
(36, 31, 'adrian', 'adrian.es', 'Activo', '2027-05-12', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ftp_cuentas_extra`
--

CREATE TABLE `ftp_cuentas_extra` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `ftp_user` varchar(50) NOT NULL,
  `ftp_pass` varchar(255) NOT NULL,
  `estado` enum('Pendiente','Tramitando','Activo','Error') DEFAULT 'Pendiente',
  `owner_ftp` varchar(100) DEFAULT NULL,
  `creado_en_so` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ftp_cuentas_extra`
--

INSERT INTO `ftp_cuentas_extra` (`id`, `user_id`, `ftp_user`, `ftp_pass`, `estado`, `owner_ftp`, `creado_en_so`) VALUES
(1, 22, 'u22_stafff', '12345678', 'Activo', NULL, 0),
(2, 22, 'u22_sftass2', '12345678', 'Activo', NULL, 0),
(3, 22, 'u22_11gmailcom', '12345678', 'Activo', NULL, 0),
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
(2, 26, 'usuario', 'hola', 'texto', '2026-05-10 01:42:32', 0, 0),
(3, 26, 'usuario', 'quiero pagina', 'texto', '2026-05-10 01:42:39', 0, 0),
(4, 11, 'usuario', 'Hola', 'texto', '2026-05-10 01:44:40', 0, 0),
(5, 30, 'usuario', 'Prueba de que funciona el chat y el log de los chat de q hay un mensaje activo, (solo logs)', 'texto', '2026-05-11 13:10:39', 0, 0),
(6, 1, 'admin', 'a', 'texto', '2026-05-12 22:55:15', 0, 0),
(7, 31, 'admin', 'Buenas Buenas', 'texto', '2026-05-12 23:15:42', 0, 0),
(8, 31, 'usuario', 'Que pasaaaaaaaa', 'texto', '2026-05-12 23:16:10', 0, 0),
(9, 31, 'usuario', 'Quiero una pagina web con ia', 'texto', '2026-05-12 23:20:49', 0, 0),
(10, 31, 'admin', 'Perfecto, como le gustaria la página?', 'texto', '2026-05-12 23:35:42', 0, 0);

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
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_plain` varchar(255) DEFAULT NULL,
  `plan_contratado` varchar(50) DEFAULT 'Ninguno',
  `estado_servicio` enum('Pendiente','Activo','Para_Borrar') DEFAULT 'Pendiente',
  `ftp_user` varchar(50) DEFAULT NULL,
  `ftp_pass` varchar(50) DEFAULT NULL,
  `storage_qty` int DEFAULT '0',
  `extras_json` text,
  `rol` varchar(20) DEFAULT 'usuario',
  `fecha_cancelacion` datetime DEFAULT NULL,
  `creado_en_so` tinyint(1) DEFAULT '0',
  `showcase_permission` tinyint(1) NOT NULL DEFAULT '1',
  `multiuser_qty` int DEFAULT '0',
  `modulo_ia` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `password_plain`, `plan_contratado`, `estado_servicio`, `ftp_user`, `ftp_pass`, `storage_qty`, `extras_json`, `rol`, `fecha_cancelacion`, `creado_en_so`, `showcase_permission`, `multiuser_qty`, `modulo_ia`) VALUES
(1, 'Elizabeth', 'elirosellbonavina@gmail.com', '$2y$10$gZ.gjAnwV35n4s3T5Zbff.5m6flMjSVlqMRH3Vb0nk9t8vwA/WIvW', '12345678', 'PROFESIONAL', 'Activo', 'elizabeth', '12345678', 0, '[\"sql_php|5.00\"]', 'admin', NULL, 1, 1, 0, 0),
(4, 'kisell', 'kisellress@gmail.com', '$2y$10$uz.DcFH1ixOqQW6e/Tdi6.J3vHU7MQ0jjkvtImAWMFb/v.6JaWxVq', '12345678', 'BÁSICO', 'Activo', 'kisell', 'pass_cf6847', 4, '[\"sql_php|5.00\",\"domain|15.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(6, 'Prueba_empresa', 'empresa@gmail.com', '$2y$10$hJx9vhTuCh8waK5ctDu.m.kxYDwX9EKZKW7kAi5BSVshg5tYSl5Ry', NULL, 'PROFESIONAL', 'Activo', 'prueba_empresa', 'pass_c4ce09', 0, '[\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(7, 'Prueba_Python', 'Python@gmail.com', '$2y$10$Ra2pQ.pqXz8pMCKjdZWkJ.WIBkwS7pYtjL24V245MKN96nill0yv2', NULL, 'ENTERPRISE', 'Activo', 'prueba_python', 'pass_01ecc6', 0, '[\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(8, 'prueba_crontab', 'crontab@gmail.com', '$2y$10$Gqw.Iq7WRLwFEmuMxaRFiersvPm7JEdufG/hN8O1IydoUUthjeUBK', NULL, 'BÁSICO', 'Activo', 'prueba_crontab', 'pass_165bc8', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(9, 'albertoaestadoaqui', 'albertoaestadoaqui@test.com', '$2y$10$zAUVoVQwyp9yXrMxJXkkp.RYfdm9jPooBbhG.Vv2uwxwc5pSb99dy', NULL, 'Ninguno', 'Activo', 'albertoaestadoaqui', 'pass_51163c', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(10, 'albertoaestadoaquidos', 'albertoaestadoaqui2@test.com', '$2y$10$nQc/t78fvs7XtDZ73MwB6.4hVRbWDCtK5ZAGWIF0tw/5MqpN8Of7K', NULL, 'Ninguno', 'Activo', 'albertoaestadoaquidos', 'pass_330fd1', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(11, 'Alex', 'alex@araaxyss.com', '$2y$10$ly.tzRNiVc/IoPsrXqYMDOIAUM5BfW74Dt5Wdi/NsBnyTWzkpy1v.', '12345678', 'Básico', 'Activo', 'alex', 'pass_ab7e23', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(12, 'Test', 'test@test.com', '$2y$10$X2bkleFVF4mGdRhu2wbI6.YT33Krq3mv6HGe0229bT4j8QKaW14qy', NULL, 'Ninguno', 'Activo', 'test', 'pass_0cb49a', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(13, 'elUsu', 'usu1@gmail.com', '$2y$10$pTerOze3CymHmxyLLGeGwuTBeUMCgd3X.Jru0QPrs9wRRIx7/Wdge', NULL, 'PROFESIONAL', 'Activo', 'elusu', 'pass_7806b5', 0, '[\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(15, 'aq', 'a@ss.s', '$2y$10$2ZTpVeF1sdlL7jzIjfd8nOmida6W2OTTmrY64zO5yBqvUp3Yb3k0m', NULL, 'Ninguno', 'Activo', 'aq', 'pass_9a1636', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(16, 'prueba_nombres_ftp', 'pruebasftp2@gmail.com', '$2y$10$nqdQep2rLO0gf6lL7xJdxO4HeemjlwPNr08JMu0ciDvfFMRqjlAm6', NULL, 'Ninguno', 'Activo', 'prueba_nombres', 'pass_075fc7', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(17, 'simple', 'simple@gmail.com', '$2y$10$CEqmXz1gDeOPxpkAYbMuyuLVjkL2VmWUZWK1lhB6ywiXNlXKhAppS', NULL, 'Ninguno', 'Activo', 'simple_ftp', 'pass_6ea7cd', 0, '[]', 'usuario', NULL, 1, 1, 0, 0),
(18, 'asasa', 'qasqq@m.s', '$2y$10$p6WP2t6aT8x1P6CHXj03auW8vGz7ubODeQGZbY6MS6qvt1MlJDtj6', NULL, 'ENTERPRISE', 'Activo', 'asasa_ftp', 'pass_7eb176', 2, '[\"web_ai|100.00\",\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 0, 0, 0),
(19, 'prueba nueva', 'pruebanueva@gmail.com', '$2y$10$dwITxgWFvfrgBVVGA8EIfuUydzjbgjMshecs4puEeqKCcvojbaFha', NULL, 'PROFESIONAL', 'Activo', 'pruebanueva_ftp', 'pass_5e1594', 0, '[\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 2, 0),
(20, 'aaasd', 'asdasda@gmail.com', '$2y$10$826XcqEeUTowIdOAaOxyeOflIgi0UKpnKeoSsSPGYOQrUTlxTqAh2', NULL, 'PROFESIONAL', 'Activo', 'aaasd_ftp', 'pass_1986a7', 0, '[\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 2, 0),
(21, 'prueba', 'pruebanueva9@gmail.com', '$2y$10$.gQey9gqj5CMGIZVPhhw8O0KUTnjkGdm44IDOyCXGaFq9cD5M9gMa', NULL, 'PROFESIONAL', 'Activo', 'prueba', 'pass_365db7', 0, '[\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 5, 0),
(22, 'staffss', 'pruebasssueva@gmail.com', '$2y$10$cO/fjBxWnfrwUXB.d4kN7uW8e9tzWFSw2bovIAofwid6FQ6GRjngi', NULL, 'ENTERPRISE', 'Activo', 'pruebastaffs', '12345678', 0, '[\"web_ai|100.00\",\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(24, 'marsell', 'marsell@gmail.com', '$2y$10$1FzTeTooNR/soiL1PKAvH.F9mDNp./iNtqX1n7ePXiykvvpEFIUmC', '12345678', 'Básico', 'Activo', 'marsell', '12345678', 0, '[\"sql_php|5.00\",\"domain|15.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(25, 'Tipico', 'tipico@gmail.com', '$2y$10$zTBTkDy6Bc7tgiM42V5B2.60BefR.L/MkqByPPWTBKpWePPLDbET2', NULL, 'BÁSICO', 'Activo', 'tipico', 'pass_48837e', 0, '[]', 'usuario', NULL, 1, 0, 0, 0),
(26, 'pag wweb', 'pagwe@gmail.com', '$2y$10$5kVeO6OMlQKBYmlz5DgZKOaceo7/WdP7Ku9NE8f6fh.zQDpfI2May', '12345678', 'PROFESIONAL', 'Activo', 'pagwweb', 'pass_b1f97f', 0, '[\"domain|15.00\",\"web_ai|100.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 0),
(29, 'Skincare', 'Skincare@gmail.com', '$2y$10$5DZstcDJDnN3qpv7YvTrkeCNd9PqSyCS.8ucKejZxwx0KVQ8/eAmK', '12345678', 'BÁSICO', 'Activo', 'skincare', 'pass_b36fc3', 0, '[]', 'usuario', NULL, 1, 0, 0, 0),
(30, 'Mariano Franco', 'Mariano@gmail.com', '$2y$10$Tu7Ldh6KayF8LpZ63SaEiOF5cZ9U4s0BE9mO3bzf/KaGnvN/6uMDq', '12345678', 'PROFESIONAL', 'Activo', 'marianofranco', 'pass_90b56c', 0, '[\"domain|15.00\",\"web_ai|100.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 1, 0),
(31, 'Adrian martin', 'adrian@gmail.com', '$2y$10$N2U.o5ci92p.B1G8kdr1lOjRCDY7GaCGnbjrBPcuQqYAeMoX8e1Ya', '12345678', 'ENTERPRISE', 'Pendiente', 'adrianmartin', 'pass_b0414a', 1, '[\"web_ai|100.00\",\"domain|15.00\",\"sql_php|5.00\"]', 'usuario', NULL, 1, 1, 0, 1);

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
  ADD KEY `fk_u_dominios` (`user_id`);

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
  ADD UNIQUE KEY `uq_user` (`user_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas_admin`
--
ALTER TABLE `alertas_admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `dominios`
--
ALTER TABLE `dominios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `ftp_cuentas_extra`
--
ALTER TABLE `ftp_cuentas_extra`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `mensajes_chat`
--
ALTER TABLE `mensajes_chat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `modulo_mysql`
--
ALTER TABLE `modulo_mysql`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
