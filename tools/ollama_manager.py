#!/usr/bin/env python3
"""
Ollama Auto-Manager for Horizon Project
Automatically manages Ollama server - starts it, monitors health, and restarts if needed
"""

import os
import sys
import time
import json
import signal
import logging
import requests
import subprocess
import threading
from pathlib import Path
from datetime import datetime
from typing import Optional, Dict, Any

class OllamaManager:
    def __init__(self, config_file: str = "ollama_config.json"):
        self.config_file = config_file
        self.config = self.load_config()
        self.ollama_process: Optional[subprocess.Popen] = None
        self.running = True
        self.health_check_interval = self.config.get("health_check_interval", 30)
        self.startup_timeout = self.config.get("startup_timeout", 60)
        self.max_restart_attempts = self.config.get("max_restart_attempts", 3)
        self.restart_attempts = 0
        
        # Setup logging
        self.setup_logging()
        
        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
        self.logger.info("Ollama Manager initialized")
        self.logger.info(f"Configuration: {json.dumps(self.config, indent=2)}")

    def setup_logging(self):
        """Setup logging configuration"""
        log_dir = Path("logs")
        log_dir.mkdir(exist_ok=True)
        
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(log_dir / "ollama_manager.log"),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)

    def load_config(self) -> Dict[str, Any]:
        """Load configuration from file or create default"""
        default_config = {
            "ollama_executable": self.find_ollama_executable(),
            "base_url": "http://127.0.0.1:11434",
            "model": "phi4-mini:3.8b",
            "health_check_interval": 30,
            "startup_timeout": 60,
            "max_restart_attempts": 3,
            "auto_start": True,
            "auto_restart": True,
            "log_level": "INFO"
        }
        
        if os.path.exists(self.config_file):
            try:
                with open(self.config_file, 'r') as f:
                    loaded_config = json.load(f)
                default_config.update(loaded_config)
            except Exception as e:
                print(f"Warning: Could not load config file {self.config_file}: {e}")
        
        # Save config for future use
        with open(self.config_file, 'w') as f:
            json.dump(default_config, f, indent=2)
        
        return default_config

    def find_ollama_executable(self) -> str:
        """Find Ollama executable in common locations"""
        possible_paths = []
        
        if sys.platform == "win32":
            possible_paths = [
                "C:\\Program Files\\Ollama\\ollama.exe",
                "C:\\Program Files (x86)\\Ollama\\ollama.exe",
                os.path.expandvars("%LOCALAPPDATA%\\Programs\\Ollama\\ollama.exe"),
                "ollama.exe"  # Try PATH
            ]
        else:
            possible_paths = [
                "/usr/local/bin/ollama",
                "/usr/bin/ollama",
                os.path.expanduser("~/.local/bin/ollama"),
                "ollama"  # Try PATH
            ]
        
        for path in possible_paths:
            if os.path.exists(path) or path == "ollama":
                return path
        
        raise FileNotFoundError("Could not find Ollama executable. Please install Ollama first.")

    def is_ollama_running(self) -> bool:
        """Check if Ollama server is running and responsive"""
        try:
            response = requests.get(
                f"{self.config['base_url']}/api/tags",
                timeout=5
            )
            return response.status_code == 200
        except Exception as e:
            self.logger.debug(f"Health check failed: {e}")
            return False

    def start_ollama(self) -> bool:
        """Start Ollama server"""
        if self.is_ollama_running():
            self.logger.info("Ollama is already running")
            return True
        
        try:
            self.logger.info("Starting Ollama server...")
            
            # Prepare command
            cmd = [self.config["ollama_executable"], "serve"]
            
            # Start Ollama process
            if sys.platform == "win32":
                # On Windows, start without creating a new window
                startupinfo = subprocess.STARTUPINFO()
                startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
                startupinfo.wShowWindow = subprocess.SW_HIDE
                
                self.ollama_process = subprocess.Popen(
                    cmd,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    startupinfo=startupinfo,
                    creationflags=subprocess.CREATE_NO_WINDOW
                )
            else:
                # On Unix-like systems
                self.ollama_process = subprocess.Popen(
                    cmd,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE
                )
            
            # Wait for startup
            self.logger.info(f"Waiting for Ollama to start (timeout: {self.startup_timeout}s)...")
            
            start_time = time.time()
            while time.time() - start_time < self.startup_timeout:
                if self.is_ollama_running():
                    self.logger.info("Ollama started successfully")
                    self.restart_attempts = 0  # Reset restart attempts
                    return True
                time.sleep(2)
                
                # Check if process died
                if self.ollama_process and self.ollama_process.poll() is not None:
                    stdout, stderr = self.ollama_process.communicate()
                    self.logger.error(f"Ollama process died during startup")
                    self.logger.error(f"STDOUT: {stdout.decode()}")
                    self.logger.error(f"STDERR: {stderr.decode()}")
                    return False
            
            self.logger.error("Ollama startup timed out")
            return False
            
        except Exception as e:
            self.logger.error(f"Failed to start Ollama: {e}")
            return False

    def stop_ollama(self):
        """Stop Ollama server"""
        if self.ollama_process:
            try:
                self.logger.info("Stopping Ollama server...")
                self.ollama_process.terminate()
                
                # Wait for graceful shutdown
                try:
                    self.ollama_process.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    self.logger.warning("Ollama didn't stop gracefully, forcing...")
                    self.ollama_process.kill()
                    self.ollama_process.wait()
                
                self.ollama_process = None
                self.logger.info("Ollama stopped")
            except Exception as e:
                self.logger.error(f"Error stopping Ollama: {e}")

    def health_monitor(self):
        """Monitor Ollama health and restart if needed"""
        while self.running:
            try:
                if not self.is_ollama_running():
                    self.logger.warning("Ollama is not running!")
                    
                    if self.config.get("auto_restart", True):
                        if self.restart_attempts < self.max_restart_attempts:
                            self.restart_attempts += 1
                            self.logger.info(f"Attempting to restart Ollama (attempt {self.restart_attempts}/{self.max_restart_attempts})")
                            
                            if self.start_ollama():
                                self.logger.info("Ollama restarted successfully")
                            else:
                                self.logger.error("Failed to restart Ollama")
                        else:
                            self.logger.error(f"Max restart attempts ({self.max_restart_attempts}) reached. Giving up.")
                            break
                    else:
                        self.logger.info("Auto-restart is disabled")
                        break
                
                time.sleep(self.health_check_interval)
                
            except Exception as e:
                self.logger.error(f"Error in health monitor: {e}")
                time.sleep(5)

    def signal_handler(self, signum, frame):
        """Handle shutdown signals"""
        self.logger.info(f"Received signal {signum}, shutting down...")
        self.running = False
        self.stop_ollama()
        sys.exit(0)

    def run(self):
        """Main run loop"""
        self.logger.info("Starting Ollama Manager...")
        
        try:
            # Start Ollama if configured to do so
            if self.config.get("auto_start", True):
                if not self.start_ollama():
                    self.logger.error("Failed to start Ollama on startup")
                    return
            
            # Start health monitoring in a separate thread
            monitor_thread = threading.Thread(target=self.health_monitor, daemon=True)
            monitor_thread.start()
            
            self.logger.info("Ollama Manager is running. Press Ctrl+C to stop.")
            
            # Keep the main thread alive
            while self.running:
                time.sleep(1)
                
        except KeyboardInterrupt:
            self.logger.info("Received keyboard interrupt")
        except Exception as e:
            self.logger.error(f"Unexpected error: {e}")
        finally:
            self.stop_ollama()
            self.logger.info("Ollama Manager stopped")

    def status(self) -> Dict[str, Any]:
        """Get current status"""
        return {
            "running": self.is_ollama_running(),
            "process_alive": self.ollama_process is not None and self.ollama_process.poll() is None,
            "config": self.config,
            "restart_attempts": self.restart_attempts,
            "timestamp": datetime.now().isoformat()
        }

def main():
    """Main entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description="Ollama Auto-Manager for Horizon Project")
    parser.add_argument("--config", default="ollama_config.json", help="Configuration file path")
    parser.add_argument("--status", action="store_true", help="Show current status and exit")
    parser.add_argument("--start", action="store_true", help="Start Ollama and exit")
    parser.add_argument("--stop", action="store_true", help="Stop Ollama and exit")
    parser.add_argument("--restart", action="store_true", help="Restart Ollama and exit")
    
    args = parser.parse_args()
    
    try:
        manager = OllamaManager(args.config)
        
        if args.status:
            status = manager.status()
            print(json.dumps(status, indent=2))
            return
        
        if args.start:
            success = manager.start_ollama()
            print(f"Ollama start: {'SUCCESS' if success else 'FAILED'}")
            return
        
        if args.stop:
            manager.stop_ollama()
            print("Ollama stopped")
            return
        
        if args.restart:
            manager.stop_ollama()
            success = manager.start_ollama()
            print(f"Ollama restart: {'SUCCESS' if success else 'FAILED'}")
            return
        
        # Default: run the manager
        manager.run()
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
