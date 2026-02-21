# Ollama Auto-Manager Setup Guide

## Overview
I've created a comprehensive Python-based Ollama management system that automatically starts, monitors, and restarts Ollama when needed. This will solve your issues with the AI agent not having Ollama running.

## 🚀 Quick Start

### Option 1: Simple Startup (Recommended for testing)
```bash
# On Windows
start-ollama-manager.bat

# On Linux/macOS
./start-ollama-manager.sh
```

### Option 2: Windows Service (Permanent solution)
```bash
# Run as Administrator
install-ollama-service.bat
```

## 📁 Files Created

### Core Components
- `tools/ollama_manager.py` - Main Python management script
- `ollama_config.json` - Configuration file
- `start-ollama-manager.bat` - Windows launcher
- `start-ollama-manager.sh` - Linux/macOS launcher

### Windows Service
- `tools/ollama_service.py` - Windows service implementation
- `install-ollama-service.bat` - Service installer

## 🔧 Features

### ✅ Automatic Management
- **Auto-start**: Starts Ollama when manager launches
- **Health monitoring**: Checks Ollama status every 30 seconds
- **Auto-restart**: Restarts Ollama if it crashes (up to 3 attempts)
- **Graceful shutdown**: Cleanly stops Ollama on exit

### ✅ Smart Detection
- **Finds Ollama**: Automatically detects Ollama installation
- **Cross-platform**: Works on Windows, Linux, and macOS
- **Fallback support**: Falls back to original method if Python manager fails

### ✅ Integration
- **AI Assistant**: Your AI assistant now tries Python manager first
- **Logging**: Detailed logs in `logs/ollama_manager.log`
- **Configuration**: Customizable settings via JSON config

## 🛠️ Configuration

Edit `ollama_config.json` to customize:

```json
{
  "health_check_interval": 30,     // Check every 30 seconds
  "startup_timeout": 60,            // Wait 60s for startup
  "max_restart_attempts": 3,       // Max 3 restart attempts
  "auto_start": true,               // Start Ollama automatically
  "auto_restart": true              // Restart if Ollama crashes
}
```

## 📋 Commands

### Using the Python Manager
```bash
# Check status
python tools/ollama_manager.py --status

# Start Ollama
python tools/ollama_manager.py --start

# Stop Ollama
python tools/ollama_manager.py --stop

# Restart Ollama
python tools/ollama_manager.py --restart

# Run continuously (recommended)
python tools/ollama_manager.py
```

### Windows Service Commands
```bash
# Install service (run as Administrator)
python tools/ollama_service.py install

# Start service
net start OllamaManager

# Stop service
net stop OllamaManager

# Remove service
python tools/ollama_service.py remove
```

## 🔍 How It Works

1. **Startup**: Manager starts and checks configuration
2. **Detection**: Finds Ollama executable automatically
3. **Launch**: Starts Ollama server in background
4. **Monitoring**: Continuously checks Ollama health
5. **Recovery**: Restarts if Ollama crashes
6. **Integration**: AI assistant uses manager for reliable startup

## 🐛 Troubleshooting

### Python Not Found
- Install Python 3.7+ from https://python.org
- Or use Windows Store Python

### Ollama Not Found
- Install Ollama from https://ollama.ai
- Make sure it's in your PATH

### Service Installation Fails
- Run installer as Administrator
- Install pywin32: `pip install pywin32`

### Permission Issues
- Run scripts with appropriate permissions
- Check firewall/antivirus settings

## 🎯 Benefits

### ✅ No More Manual Ollama Management
- Ollama starts automatically with your system
- No more "Ollama not running" errors
- Reliable AI assistant operation

### ✅ Better Reliability
- Automatic crash recovery
- Health monitoring and alerts
- Detailed logging for debugging

### ✅ Easy Setup
- One-click installation
- Automatic configuration
- Cross-platform support

## 🔄 Next Steps

1. **Test the simple startup** first with `start-ollama-manager.bat`
2. **Verify AI assistant works** with Ollama running
3. **Install Windows service** for permanent solution
4. **Configure settings** to your preferences

Your Ollama issues should now be completely resolved! The AI assistant will always have a running Ollama server to connect to.
