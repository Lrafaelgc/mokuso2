-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-09-2025 a las 15:43:42
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `mokuso`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `telefono_emergencia` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT 'default.png',
  `peso` decimal(5,2) DEFAULT NULL,
  `estatura` decimal(5,2) DEFAULT NULL,
  `talla_dojo` varchar(10) DEFAULT NULL,
  `nivel` varchar(50) DEFAULT 'Cinta Blanca',
  `disciplina` varchar(50) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado_membresia` enum('activa','inactiva','pendiente') DEFAULT 'pendiente',
  `fecha_vencimiento_membresia` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `nombre`, `apellidos`, `fecha_nacimiento`, `telefono`, `telefono_emergencia`, `email`, `foto_perfil`, `peso`, `estatura`, `talla_dojo`, `nivel`, `disciplina`, `fecha_registro`, `estado_membresia`, `fecha_vencimiento_membresia`) VALUES
(1, 'Jose Rafael ', 'Gomez Ceja', '2021-10-27', '6531659955', NULL, 'lrafaelgc23@gmail.com', '1756849452_rafita.jpg', 17.00, 1.10, 'm', 'Cinta Blanca', NULL, '2025-09-02 21:44:12', 'activa', NULL),
(2, 'Julio Cesar ', 'Martinez Aguilar', '2016-06-12', '653165956', '6531173158', 'helaman@gmail.com', '1756849745_julio.jpg', 25.00, 1.50, 'g', 'Cinta Blanca', 'Jiu jitsu B', '2025-09-02 21:49:05', 'inactiva', '2025-09-01'),
(3, 'Analy', 'Gomez ', '2024-03-19', '6531659955', NULL, 'jiapssy@gmail.com', '1756850835_IMG_6601.jpeg', 8.00, 0.50, 'S', 'Cinta Blanca', NULL, '2025-09-02 22:07:15', 'activa', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `fecha_asistencia` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `asistencias`
--

INSERT INTO `asistencias` (`id`, `alumno_id`, `fecha_asistencia`) VALUES
(1, 1, '2025-09-02'),
(4, 1, '2025-09-02'),
(5, 3, '2025-09-02'),
(6, 2, '2025-09-02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logros`
--

CREATE TABLE `logros` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `logro` varchar(255) NOT NULL,
  `fecha_logro` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `logros`
--

INSERT INTO `logros` (`id`, `alumno_id`, `logro`, `fecha_logro`) VALUES
(1, 3, 'Primer lugar en peleaas', '2025-09-02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` date NOT NULL,
  `mes_correspondiente` varchar(20) DEFAULT NULL,
  `concepto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `alumno_id`, `monto`, `fecha_pago`, `mes_correspondiente`, `concepto`) VALUES
(1, 1, 600.00, '2025-09-02', 'September 2025', 'Mensualidad Regular'),
(2, 2, 600.00, '2025-06-01', 'june 2025', 'Mensualidad Regular'),
(3, 3, 400.00, '2025-09-02', 'September 2025', 'Promoción Primer Mes ($400)'),
(4, 2, 600.00, '2025-09-02', 'September 2025', 'Mensualidad Regular ($600)');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- Indices de la tabla `logros`
--
ALTER TABLE `logros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `asistencias`
--
ALTER TABLE `asistencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `logros`
--
ALTER TABLE `logros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencias`
--
ALTER TABLE `asistencias`
  ADD CONSTRAINT `asistencias_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `logros`
--
ALTER TABLE `logros`
  ADD CONSTRAINT `logros_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
