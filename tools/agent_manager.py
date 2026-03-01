#!/usr/bin/env python3
"""
Syndicati Agent Auto-Manager
Automatically starts and manages the Playwright agent server
"""

import os
import sys
import time
import json
import subprocess
import signal
import socket
from pathlib import Path
from datetime import datetime

AGENT_PORT = 3002
PID_FILE = Path(sys.path[0]).parent / 'var' / 'agent_server.pid'
LOG_FILE = Path(sys.path[0]).parent / 'var' / 'agent_server.log'

def log(msg):
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{timestamp}] {msg}"
    print(line)
    try:
        LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
        with open(LOG_FILE, 'a') as f:
            f.write(line + '\n')
    except:
        pass

def is_port_open(port):
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(2)
        result = sock.connect_ex(('127.0.0.1', port))
        sock.close()
        return result == 0
    except:
        return False

def get_agent_pid():
    if PID_FILE.exists():
        try:
            return int(PID_FILE.read_text().strip())
        except:
            return None
    return None

def is_agent_running():
    # Check if port is open
    if is_port_open(AGENT_PORT):
        return True
    
    # Check PID file
    pid = get_agent_pid()
    if pid:
        try:
            import psutil
            process = psutil.Process(pid)
            if process.is_running() and 'agent-server' in ' '.join(process.cmdline()):
                return True
        except:
            pass
    
    return False

def find_nodejs():
    """Find Node.js executable"""
    if sys.platform == 'win32':
        candidates = ['node.exe', 'node']
        # Check common paths
        paths = [
            r'C:\Program Files\nodejs\node.exe',
            r'C:\Program Files (x86)\nodejs\node.exe',
            os.path.expandvars(r'%APPDATA%\npm\node.exe'),
        ]
        for p in paths:
            if os.path.exists(p):
                return p
    else:
        candidates = ['node', 'nodejs']
    
    # Try PATH
    for cmd in candidates:
        try:
            result = subprocess.run([cmd, '--version'], capture_output=True, timeout=5)
            if result.returncode == 0:
                return cmd
        except:
            pass
    
    return None

def start_agent():
    """Start the agent server in background"""
    if is_agent_running():
        log("Agent server is already running")
        return True
    
    node = find_nodejs()
    if not node:
        log("ERROR: Node.js not found!")
        return False
    
    project_root = Path(sys.path[0]).parent
    agent_script = project_root / 'playwright' / 'agent-server.js'
    
    if not agent_script.exists():
        log(f"ERROR: Agent script not found at {agent_script}")
        return False
    
    log(f"Starting agent server with Node.js: {node}")
    log(f"Agent script: {agent_script}")
    
    try:
        if sys.platform == 'win32':
            # Windows - use CREATE_NO_WINDOW flag
            CREATE_NO_WINDOW = 0x08000000
            DETACHED_PROCESS = 0x00000008
            
            process = subprocess.Popen(
                [node, str(agent_script)],
                cwd=str(project_root / 'playwright'),
                creationflags=CREATE_NO_WINDOW | DETACHED_PROCESS,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL,
                stdin=subprocess.DEVNULL,
                close_fds=True
            )
        else:
            # Unix - double fork
            pid = os.fork()
            if pid > 0:
                # Parent process
                time.sleep(2)
                return is_agent_running()
            
            os.setsid()
            pid = os.fork()
            if pid > 0:
                os._exit(0)
            
            # Child process
            os.chdir(str(project_root / 'playwright'))
            os.umask(0)
            
            # Redirect streams
            devnull = os.open('/dev/null', os.O_RDWR)
            os.dup2(devnull, 0)
            os.dup2(devnull, 1)
            os.dup2(devnull, 2)
            
            os.execvp(node, [node, str(agent_script)])
        
        # Save PID
        PID_FILE.parent.mkdir(parents=True, exist_ok=True)
        PID_FILE.write_text(str(process.pid))
        
        log(f"Agent server started with PID: {process.pid}")
        
        # Wait for it to be ready
        for i in range(10):
            time.sleep(1)
            if is_agent_running():
                log("Agent server is ready!")
                return True
        
        log("WARNING: Agent server may not have started properly")
        return False
        
    except Exception as e:
        log(f"ERROR starting agent: {e}")
        return False

def stop_agent():
    """Stop the agent server"""
    pid = get_agent_pid()
    
    if pid:
        try:
            if sys.platform == 'win32':
                subprocess.run(['taskkill', '/PID', str(pid), '/F'], capture_output=True)
            else:
                os.kill(pid, signal.SIGTERM)
                time.sleep(2)
                try:
                    os.kill(pid, signal.SIGKILL)
                except:
                    pass
        except Exception as e:
            log(f"Error stopping agent: {e}")
    
    # Also kill by port
    if sys.platform == 'win32':
        try:
            result = subprocess.run(
                f'netstat -ano | findstr :{AGENT_PORT}',
                shell=True, capture_output=True, text=True
            )
            for line in result.stdout.split('\n'):
                if 'LISTENING' in line:
                    parts = line.split()
                    if parts:
                        pid = parts[-1]
                        subprocess.run(['taskkill', '/PID', pid, '/F'], capture_output=True)
        except:
            pass
    
    if PID_FILE.exists():
        PID_FILE.unlink()
    
    log("Agent server stopped")
    return True

def get_status():
    """Get agent server status"""
    running = is_agent_running()
    pid = get_agent_pid()
    
    return {
        'running': running,
        'pid': pid,
        'port': AGENT_PORT,
    }

def main():
    if len(sys.argv) < 2:
        print("Usage: python agent_manager.py <start|stop|restart|status>")
        sys.exit(1)
    
    command = sys.argv[1].lower()
    
    if command == 'start':
        if start_agent():
            print(json.dumps({'success': True, 'status': 'started'}))
            sys.exit(0)
        else:
            print(json.dumps({'success': False, 'error': 'Failed to start'}))
            sys.exit(1)
    
    elif command == 'stop':
        stop_agent()
        print(json.dumps({'success': True, 'status': 'stopped'}))
        sys.exit(0)
    
    elif command == 'restart':
        stop_agent()
        time.sleep(2)
        if start_agent():
            print(json.dumps({'success': True, 'status': 'restarted'}))
            sys.exit(0)
        else:
            print(json.dumps({'success': False, 'error': 'Failed to restart'}))
            sys.exit(1)
    
    elif command == 'status':
        status = get_status()
        print(json.dumps(status))
        sys.exit(0 if status['running'] else 1)
    
    else:
        print(f"Unknown command: {command}")
        print("Usage: python agent_manager.py <start|stop|restart|status>")
        sys.exit(1)

if __name__ == '__main__':
    main()
