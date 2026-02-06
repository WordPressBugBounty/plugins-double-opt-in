<?php

namespace Forge12\Shared;

if ( ! interface_exists( 'Forge12\Shared\LoggerInterface' ) ) {
	interface LoggerInterface {
		public function debug( string $message, array $context = [] ): void;

		public function info( string $message, array $context = [] ): void;

		public function error( string $message, array $context = [] ): void;

		public function critical( string $message, array $context = [] ): void;

		public function warning( string $message, array $context = [] ): void;

		public function notice( string $message, array $context = [] ): void; // Füge diese Zeile hinzu
	}
}