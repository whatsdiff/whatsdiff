#!/bin/bash
set -e

# Initialize default binary names
binary_name="whatsdiff"
binary_mcp_name="whatsdiff-mcp"

# Parse command line arguments
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --name) binary_name="$2"; shift ;; # Get the new binary name
        --mcp-name) binary_mcp_name="$2"; shift ;; # Get the new MCP binary name
        *) echo "Unknown parameter passed: $1"; exit 1 ;;
    esac
    shift
done

# Removing old build files
rm -rf build/bin/
#rm -rf build/buildroot/
#rm -rf build/downloads/
#rm -rf build/source/
#rm -rf build/static-php-cli/

# Directories
mkdir -p build/bin/

# Build both PHAR files using box
echo "Building whatsdiff.phar..."
./vendor/bin/box compile --config=box.json

echo "Building whatsdiff-mcp.phar..."
./vendor/bin/box compile --config=box-mcp.json

# Fetch or update static-php-cli
if [ -d "build/static-php-cli" ]; then
  cd build/static-php-cli/
#  git reset --hard HEAD
  git pull
else
  cd build/
  git clone --depth 1 https://github.com/crazywhalecc/static-php-cli.git
  cd static-php-cli/
fi

# Install dependencies
composer install --no-interaction
chmod +x bin/spc

# Back to main directory
cd ../

# Build PHP Micro with only the extensions we need
./static-php-cli/bin/spc doctor --auto-fix
./static-php-cli/bin/spc download --with-php="8.4" --for-extensions="ctype,curl,dom,filter,libxml,mbstring,openssl,pcntl,phar,posix,simplexml,sockets,xml,xmlwriter,zlib" --prefer-pre-built
./static-php-cli/bin/spc switch-php-version "8.4"
./static-php-cli/bin/spc build --build-micro "ctype,curl,dom,filter,libxml,mbstring,openssl,pcntl,phar,posix,simplexml,sockets,xml,xmlwriter,zlib"

# Build binaries by combining PHARs with micro.sfx
echo "Building $binary_name binary..."
./static-php-cli/bin/spc micro:combine bin/whatsdiff.phar --output="bin/$binary_name"
chmod 0755 "bin/$binary_name"

echo "Building $binary_mcp_name binary..."
./static-php-cli/bin/spc micro:combine bin/whatsdiff-mcp.phar --output="bin/$binary_mcp_name"
chmod 0755 "bin/$binary_mcp_name"

echo "Build complete!"
echo "  - bin/$binary_name"
echo "  - bin/$binary_mcp_name"
