#!/bin/bash
# Ollama Auto-Manager Launcher for Linux/macOS
# This script starts the Python Ollama manager automatically

echo "Starting Ollama Auto-Manager..."

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is not installed or not in PATH"
    echo "Please install Python 3.7+ from your package manager"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "tools/ollama_manager.py" ]; then
    echo "Error: ollama_manager.py not found in tools directory"
    echo "Please run this script from the Horizon project root"
    exit 1
fi

# Create logs directory if it doesn't exist
mkdir -p logs

# Install required Python packages if not already installed
echo "Checking Python dependencies..."
python3 -c "import requests" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "Installing requests package..."
    pip3 install requests
fi

# Start the Ollama manager
echo "Starting Ollama manager..."
python3 tools/ollama_manager.py
