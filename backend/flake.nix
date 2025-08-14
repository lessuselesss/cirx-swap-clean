{
  description = "CIRX OTC Backend PHP Development Environment";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = nixpkgs.legacyPackages.${system};
        
        # PHP with required extensions
        php = pkgs.php82.buildEnv {
          extensions = ({ enabled, all }: enabled ++ (with all; [
            bcmath
            curl
            dom
            mbstring
            openssl
            pdo
            pdo_mysql
            pdo_sqlite
            session
            xml
            zip
          ]));
        };

        # Custom PHP development script
        devScript = pkgs.writeScriptBin "dev-server" ''
          #!${pkgs.bash}/bin/bash
          echo "Starting CIRX OTC Backend Development Server..."
          echo "Server will be available at http://localhost:8080"
          cd "$(dirname "$0")"
          ${php}/bin/php -S localhost:8080 -t public
        '';

        # Test runner script
        testScript = pkgs.writeScriptBin "run-tests" ''
          #!${pkgs.bash}/bin/bash
          echo "Running PHPUnit tests..."
          cd "$(dirname "$0")"
          ${php}/bin/php vendor/bin/phpunit "$@"
        '';

      in
      {
        devShells.default = pkgs.mkShell {
          buildInputs = with pkgs; [
            php
            php82Packages.composer
            mysql80
            sqlite
            devScript
            testScript
            # Development tools
            phpunit
            # Database tools
            mysql-workbench
            # HTTP testing
            httpie
            curl
          ];

          shellHook = ''
            echo "🚀 CIRX OTC Backend Development Environment"
            echo "================================================="
            echo "PHP Version: $(php --version | head -n1)"
            echo "Composer Version: $(composer --version)"
            echo ""
            echo "📦 Setup Commands:"
            echo "  composer install    - Install PHP dependencies"
            echo "  php artisan migrate - Run database migrations"
            echo ""
            echo "🛠️ Development Commands:"
            echo "  dev-server          - Start development server (localhost:8080)"
            echo "  run-tests           - Run PHPUnit tests"
            echo "  php artisan migrate:status - Check migration status"
            echo ""
            echo "🗄️ Database Migration Commands:"
            echo "  php artisan migrate         - Run pending migrations"
            echo "  php artisan migrate:rollback - Rollback last migration batch"
            echo "  php artisan migrate:fresh   - Reset and re-run all migrations"
            echo "  php artisan migrate:status  - Show migration status"
            echo ""
            echo "⚙️ Background Workers:"
            echo "  php artisan worker          - Run payment verification + CIRX transfer workers"
            echo "  php artisan worker:stats    - Show worker statistics"
            echo "  php worker.php both         - Alternative worker command"
            echo ""
            echo "🔍 Database Tools:"
            echo "  mysql               - Connect to MySQL"
            echo "  sqlite3 storage/database.sqlite - Connect to SQLite"
            echo ""
            
            # Set up environment
            export PHP_INI_DIR="${php}/etc"
            export COMPOSER_HOME="$PWD/.composer"
            
            # Create necessary directories
            mkdir -p storage/logs
            mkdir -p .composer
            
            echo "Ready for development! 🎯"
          '';
        };

        # Package for production builds
        packages.default = pkgs.stdenv.mkDerivation {
          pname = "cirx-otc-backend";
          version = "1.0.0";
          
          src = ./.;
          
          buildInputs = [ php pkgs.php82Packages.composer ];
          
          buildPhase = ''
            composer install --no-dev --optimize-autoloader
          '';
          
          installPhase = ''
            mkdir -p $out
            cp -r . $out/
          '';
        };

        # Development tools as apps
        apps = {
          dev-server = flake-utils.lib.mkApp {
            drv = devScript;
          };
          
          test = flake-utils.lib.mkApp {
            drv = testScript;
          };
          
          composer = flake-utils.lib.mkApp {
            drv = pkgs.php82Packages.composer;
          };
        };
      });
}