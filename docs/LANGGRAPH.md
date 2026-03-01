# LangGraph Integration for Horizon AI

This document describes the LangGraph.js integration that enhances the Syndicati AI Agent with multi-step reasoning workflows.

## Overview

LangGraph is a library for building stateful, multi-agent applications with LangChain. It enables:

- **Multi-step reasoning**: The AI can break down complex tasks into steps
- **Stateful workflows**: Conversation state persists across interactions
- **Tool integration**: The AI can use tools like browsing, navigation, analysis
- **Conditional logic**: Different paths based on user intent
- **Streaming**: Real-time updates as the AI processes

## Architecture

```
┌─────────────────┐     HTTP      ┌──────────────────┐     HTTP      ┌─────────────┐
│  PHP Backend    │ ◄────────────► │  LangGraph       │ ◄────────────► │   Ollama    │
│  (Symfony)      │                │  Node.js Server  │                │   (AI)      │
└─────────────────┘                └──────────────────┘                └─────────────┘
        │                                    │
        │                                    │
        ▼                                    ▼
┌─────────────────┐                ┌──────────────────┐
│  SmartRoute     │                │  Multi-step      │
│  Mapping        │                │  Workflow:       │
│  Service        │                │  1. Understand   │
│                 │                │  2. Gather       │
│                 │                │  3. Analyze      │
│                 │                │  4. Respond      │
└─────────────────┘                └──────────────────┘
```

## Setup Instructions

### 1. Install Node.js Dependencies

```bash
# Navigate to project root
cd c:\wamp64\www\Horizon

# Install dependencies
npm install

# Or if you prefer yarn
yarn install
```

### 2. Start the LangGraph Server

```bash
# Start the LangGraph Node.js server
npm start
# or
npm run dev
```

The server will start on `http://localhost:3001` by default.

### 3. Verify Installation

Check the LangGraph server health:
```bash
curl http://localhost:3001/health
```

Expected response:
```json
{
  "status": "ok",
  "service": "langgraph-server",
  "version": "1.0.0"
}
```

Check Ollama + LangGraph status:
```bash
curl http://localhost:3001/status
```

## How It Works

### Multi-Step Workflow

When a user sends a message, the LangGraph workflow executes:

1. **Understand Intent** (`understand_intent` node)
   - Classifies: INFORMATION_QUERY, NAVIGATION, ANALYSIS, SIMPLE_CHAT
   - Extracts entities and determines if browsing is needed

2. **Route Decision** (conditional edges)
   - Based on intent, routes to appropriate node

3. **Gather Data** (`gather_data` node) - for INFORMATION_QUERY
   - Decides which pages to browse
   - Calls `browse_page` tool

4. **Analyze Data** (`analyze_data` node)
   - Processes gathered information
   - Extracts insights

5. **Execute Navigation** (`execute_navigation` node) - for NAVIGATION
   - Calls `navigate` tool
   - Confirms navigation action

6. **Formulate Response** (`formulate_response` node)
   - Generates final natural language response
   - Includes any navigation actions

### Available Tools

The LangGraph agent has these tools:

1. **browse_page**
   - Browse a specific URL to gather information
   - Actions: read, search, list

2. **navigate**
   - Navigate user to a different page
   - Provides destination and reason

3. **search_information**
   - Search across multiple pages
   - Example: search forum + residences + events

4. **analyze_data**
   - Analyze content with different analysis types
   - Types: summary, comparison, trends, details

### State Management

Each user session has state:
```javascript
{
  messages: [],           // Conversation history
  userIntent: null,       // Classified intent
  requiresBrowsing: false,
  requiresNavigation: false,
  gatheredData: [],       // Data from browsing
  finalResponse: null     // Final output
}
```

## Configuration

### Environment Variables

Add to your `.env` file:

```env
# LangGraph Configuration
LANGGRAPH_PORT=3001
LANGGRAPH_ENABLED=true

# Ollama (already configured)
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=phi4-mini:3.8b
```

### PHP Service Configuration

The `LangChainAIClient` automatically detects LangGraph availability:

1. Tries LangGraph first (if server is running)
2. Falls back to regular LangChain if LangGraph fails

This is handled transparently - no configuration needed!

## API Endpoints

### LangGraph Server Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check |
| `/status` | GET | Ollama + LangGraph status |
| `/session/init` | POST | Initialize new session |
| `/chat` | POST | Process message (sync) |
| `/chat/stream` | POST | Process message (stream) |
| `/session/clear` | POST | Clear session |
| `/admin/sessions` | GET | List active sessions |

### Example: Process Message

```bash
curl -X POST http://localhost:3001/chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "What are the latest forum posts?",
    "page_context": {
      "url": "http://localhost:8000/",
      "title": "Horizon - Home"
    }
  }'
```

Response:
```json
{
  "success": true,
  "reply": "I found the latest forum posts...",
  "actions": [],
  "intent": {
    "intent": "INFORMATION_QUERY",
    "confidence": 0.95,
    "requiresBrowsing": true
  },
  "steps": 5
}
```

## Frontend Integration

The frontend automatically detects LangGraph availability from the bootstrap response:

```javascript
// From /ai/bootstrap
{
  "langgraph": true,        // LangGraph is available
  "sessions_active": 3,      // Active LangGraph sessions
  // ...
}
```

### Streaming Support

For real-time updates during multi-step processing:

```javascript
const eventSource = new EventSource('/ai/chat/stream');
eventSource.onmessage = (event) => {
  const update = JSON.parse(event.data);
  console.log('Step:', update.node);  // Current workflow node
};
```

## Fallback Behavior

If LangGraph server is not running:
1. PHP detects unavailability on first request
2. Automatically falls back to regular LangChain
3. No user-facing errors

To force fallback, set in `.env`:
```env
LANGGRAPH_ENABLED=false
```

## Debugging

### Check LangGraph Logs

```bash
# Terminal where you ran `npm start`
# Look for:
# - Session creation
# - Workflow steps
# - Tool calls
```

### PHP Logs

LangGraph attempts and fallbacks are logged:
```bash
tail -f var/log/dev.log | grep LangGraph
```

### Direct Testing

Test workflow directly:
```bash
# Initialize session
curl -X POST http://localhost:3001/session/init

# Process message
curl -X POST http://localhost:3001/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "go to profile", "session_id": "YOUR_SESSION_ID"}'
```

## Advanced: Custom Workflows

You can extend `langgraph-agent.js` to add custom nodes:

```javascript
// Add custom node
workflow.addNode('custom_action', customActionNode.bind(this));

// Add conditional routing
workflow.addConditionalEdges(
  'decide_action',
  customRouter.bind(this),
  {
    custom: 'custom_action',
    default: 'formulate_response'
  }
);
```

## Troubleshooting

### LangGraph server won't start

```bash
# Check Node.js version (need 18+)
node --version

# Reinstall dependencies
rm -rf node_modules
npm install

# Check port availability
netstat -an | findstr 3001
```

### PHP can't connect to LangGraph

```bash
# Verify server is running
curl http://localhost:3001/health

# Check firewall settings
# Ensure port 3001 is open
```

### Out of memory errors

LangGraph workflows can use more memory. Increase limit:
```bash
node --max-old-space-size=4096 assets/langgraph-server.js
```

## Performance

- **Session timeout**: 30 minutes of inactivity
- **Cleanup interval**: Every 5 minutes
- **Max recursion**: 10 steps per workflow
- **Timeout**: 60 seconds per request

## Security

- Sessions are isolated by session ID
- No sensitive data stored in LangGraph server
- All authentication handled by PHP
- Tool calls validated before execution
