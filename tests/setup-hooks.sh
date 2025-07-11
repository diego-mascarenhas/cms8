#!/bin/bash

# Setup script for Laravel Pint Git hooks
# Run this script to enable automatic code formatting on commit

echo "🔧 Setting up Laravel Pint Git hooks..."

# Verificar que estamos en un repositorio Git
if [ ! -d ".git" ]; then
    echo "❌ This is not a Git repository. Please run this script from the project root."
    exit 1
fi

# Verificar que Pint esté instalado
if [ ! -f "./vendor/bin/pint" ]; then
    echo "❌ Laravel Pint not found. Installing..."
    composer require laravel/pint --dev
fi

# Configurar Git para usar el directorio de hooks compartido
echo "📁 Configuring Git to use shared hooks directory..."
git config core.hooksPath .hooks

# Verificar que el hook existe y es ejecutable
if [ ! -f ".hooks/pre-commit" ]; then
    echo "❌ Pre-commit hook not found in .hooks/ directory"
    exit 1
fi

if [ ! -x ".hooks/pre-commit" ]; then
    echo "🔧 Making pre-commit hook executable..."
    chmod +x .hooks/pre-commit
fi

# Configurar Pint si no existe configuración
if [ ! -f "pint.json" ]; then
    echo "⚙️ Creating default Pint configuration..."
    echo "Please ensure pint.json is created with your preferred rules."
fi

echo "✅ Git hooks setup complete!"
echo ""
echo "📋 What's configured:"
echo "   • Pre-commit hook will automatically format PHP files"
echo "   • Laravel Pint will use the configuration in pint.json"
echo "   • Only staged PHP files will be formatted"
echo ""
echo "🧪 To test the setup, try:"
echo "   git add some-file.php"
echo "   git commit -m 'test commit'"
echo ""
echo "🎉 Happy coding with automatic formatting!" 