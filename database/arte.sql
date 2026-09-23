-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para arte
CREATE DATABASE IF NOT EXISTS `arte` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `arte`;

-- Volcando estructura para tabla arte.artistas
CREATE TABLE IF NOT EXISTS `artistas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `bio` text,
  `foto` varchar(255) DEFAULT NULL,
  `usuario_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_artista_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.artistas: ~2 rows (aproximadamente)
INSERT INTO `artistas` (`id`, `nombre`, `bio`, `foto`, `usuario_id`, `created_at`) VALUES
	(1, 'Jarpy', 'paisaje', 'assets/images/artistas/809511ec1e61d96f895800542e7f75d5.jpg', 2, '2026-09-15 19:38:34'),
	(2, 'Jarpy', 'Artista independiente', 'assets/images/artistas/3d1ce38c363d5c61ced0e5c72fc4f763.jpg', 1, '2026-09-15 20:21:26'),
	(3, 'juan ignacio', 'ARTISTA', 'assets/images/artistas/8f7eec9dc3e117661ec931d7906cafa2.jpg', 3, '2026-09-15 21:29:31');

-- Volcando estructura para tabla arte.carrito_items
CREATE TABLE IF NOT EXISTS `carrito_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `obra_id` int NOT NULL,
  `cantidad` int NOT NULL DEFAULT '1',
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_obra` (`usuario_id`,`obra_id`),
  KEY `fk_carrito_obra` (`obra_id`),
  CONSTRAINT `fk_carrito_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carrito_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.carrito_items: ~0 rows (aproximadamente)

-- Volcando estructura para tabla arte.categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.categorias: ~7 rows (aproximadamente)
INSERT INTO `categorias` (`id`, `nombre`) VALUES
	(6, 'Artesanía'),
	(5, 'Cerámica'),
	(2, 'Escultura'),
	(3, 'Fotografía'),
	(4, 'Ilustración digital'),
	(7, 'pepe'),
	(1, 'Pintura');

-- Volcando estructura para tabla arte.envios
CREATE TABLE IF NOT EXISTS `envios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `metodo` enum('retiro','domicilio') NOT NULL,
  `estado` enum('pendiente','preparando','enviado','entregado') NOT NULL DEFAULT 'pendiente',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pedido_id` (`pedido_id`),
  CONSTRAINT `fk_envio_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.envios: ~0 rows (aproximadamente)
INSERT INTO `envios` (`id`, `pedido_id`, `direccion`, `metodo`, `estado`, `created_at`) VALUES
	(1, 4, 'Gutiérrez 1011', 'domicilio', 'pendiente', '2026-09-22 20:34:19');

-- Volcando estructura para tabla arte.obras
CREATE TABLE IF NOT EXISTS `obras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text,
  `precio` decimal(10,2) NOT NULL,
  `tipo` enum('digital','fisica') NOT NULL,
  `stock` int DEFAULT NULL,
  `categoria_id` int NOT NULL,
  `artista_id` int NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_artista` (`artista_id`),
  CONSTRAINT `fk_obra_artista` FOREIGN KEY (`artista_id`) REFERENCES `artistas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_obra_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.obras: ~1 rows (aproximadamente)
INSERT INTO `obras` (`id`, `titulo`, `descripcion`, `precio`, `tipo`, `stock`, `categoria_id`, `artista_id`, `imagen`, `disponible`, `created_at`) VALUES
	(10, 'frutiger paisaje', 'Paisaje idealizado de tecnologia y naturaleza', 100.00, 'fisica', 0, 4, 2, 'assets/images/obras/8536fa106eb0aa804e6697666fb25abb.webp', 1, '2026-09-22 16:54:27'),
	(11, 'ppppppppppppppppppppp', 'ghygyyyyyyyyyyyyyyyyyyyy', 1.20, 'digital', NULL, 6, 2, 'assets/images/obras/52478c3be3abd49808faee9d09ecddfe.webp', 1, '2026-09-22 21:26:59');

-- Volcando estructura para tabla arte.pedidos
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `estado` enum('pendiente','pagado','cancelado') NOT NULL DEFAULT 'pendiente',
  `total` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pedido_usuario` (`usuario_id`),
  CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.pedidos: ~3 rows (aproximadamente)
INSERT INTO `pedidos` (`id`, `usuario_id`, `estado`, `total`, `created_at`) VALUES
	(1, 1, 'pagado', 100.00, '2026-09-22 18:43:56'),
	(2, 1, 'pagado', 200.00, '2026-09-22 18:58:40'),
	(3, 1, 'pagado', 100.00, '2026-09-22 19:31:21'),
	(4, 1, 'pagado', 100.00, '2026-09-22 20:34:19');

-- Volcando estructura para tabla arte.pedido_items
CREATE TABLE IF NOT EXISTS `pedido_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `obra_id` int DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `tipo` enum('digital','fisica') NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_item_pedido` (`pedido_id`),
  KEY `fk_item_obra` (`obra_id`),
  CONSTRAINT `fk_item_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_item_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.pedido_items: ~3 rows (aproximadamente)
INSERT INTO `pedido_items` (`id`, `pedido_id`, `obra_id`, `titulo`, `tipo`, `precio_unitario`, `cantidad`) VALUES
	(1, 1, 10, 'frutiger paisaje', 'digital', 100.00, 1),
	(2, 2, 10, 'frutiger paisaje', 'digital', 100.00, 2),
	(3, 3, 10, 'frutiger paisaje', 'digital', 100.00, 1),
	(4, 4, 10, 'frutiger paisaje', 'fisica', 100.00, 1);

-- Volcando estructura para tabla arte.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Volcando datos para la tabla arte.usuarios: ~3 rows (aproximadamente)
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `rol`, `created_at`) VALUES
	(1, 'jairo', 'pepe@gmail.com', '$2y$10$ODq8k8XA4uQFNaDD02mGzOQAMX28NdUBkOBmv6DiEjmoR7Gekx/GG', 'cliente', '2026-09-04 21:27:27'),
	(2, 'Jarpy', 'pepe1@gmail.com', '$2y$10$srRGfNI1p1cuOYaRhq/hB.w/U3EQ3FSVWje0pgoBcI1/sD78w6OOq', 'admin', '2026-09-10 20:44:11'),
	(3, 'juan ignacio', 'juanignacio@gmail.com', '$2y$10$Ew3IYo2t5.2kS3fPW0QAOuJKgVpv9xEaEOEz9Zh8zxyUF2XdH4kWG', 'cliente', '2026-09-15 21:28:31');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
