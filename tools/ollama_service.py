#!/usr/bin/env python3
"""
Windows Service for Ollama Auto-Manager
Runs Ollama manager as a Windows service for automatic startup
"""

import sys
import os
import time
import servicemanager
import win32service
import win32serviceutil
import win32event
import win32api
import win32con

# Add the project root to Python path
script_dir = os.path.dirname(os.path.abspath(__file__))
project_root = os.path.dirname(script_dir)
sys.path.insert(0, project_root)

from tools.ollama_manager import OllamaManager

class OllamaManagerService(win32serviceutil.ServiceFramework):
    _svc_name_ = "OllamaManager"
    _svc_display_name_ = "Ollama Auto-Manager Service"
    _svc_description_ = "Automatically manages Ollama server for Horizon project"

    def __init__(self, args):
        win32serviceutil.ServiceFramework.__init__(self, args)
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)
        self.manager = None
        self.is_alive = True

    def SvcStop(self):
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        win32event.SetEvent(self.hWaitStop)
        self.is_alive = False
        if self.manager:
            self.manager.running = False
            self.manager.stop_ollama()

    def SvcDoRun(self):
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION_TYPE,
            servicemanager.PYS_SERVICE_STARTED,
            (self._svc_name_, '')
        )
        
        try:
            # Change to project directory
            os.chdir(project_root)
            
            # Initialize and run Ollama manager
            config_file = os.path.join(project_root, "ollama_config.json")
            self.manager = OllamaManager(config_file)
            
            # Start Ollama
            if self.manager.config.get("auto_start", True):
                if not self.manager.start_ollama():
                    servicemanager.LogErrorMsg("Failed to start Ollama")
                    return
            
            # Main service loop
            while self.is_alive:
                # Check for stop event
                if win32event.WaitForSingleObject(self.hWaitStop, 1000) == win32event.WAIT_OBJECT_0:
                    break
                
                # Health check
                if not self.manager.is_ollama_running():
                    if self.manager.config.get("auto_restart", True):
                        if self.manager.restart_attempts < self.manager.max_restart_attempts:
                            servicemanager.LogInfoMsg(f"Restarting Ollama (attempt {self.manager.restart_attempts + 1})")
                            self.manager.restart_attempts += 1
                            self.manager.start_ollama()
                        else:
                            servicemanager.LogErrorMsg("Max restart attempts reached")
                            break
                
                time.sleep(5)
                
        except Exception as e:
            servicemanager.LogErrorMsg(f"Service error: {str(e)}")
        finally:
            if self.manager:
                self.manager.stop_ollama()
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STOPPED,
                (self._svc_name_, '')
            )

if __name__ == '__main__':
    if len(sys.argv) == 1:
        servicemanager.Initialize()
        servicemanager.PrepareToHostSingle(OllamaManagerService)
        servicemanager.StartServiceCtrlDispatcher()
    else:
        win32serviceutil.HandleCommandLine(OllamaManagerService)
