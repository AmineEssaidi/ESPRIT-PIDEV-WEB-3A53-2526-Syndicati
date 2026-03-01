import express from 'express';
import cors from 'cors';
import { v4 as uuidv4 } from 'uuid';
import { SyndicatiLangGraphAgent } from './langgraph-agent.js';

/**
 * LangGraph Server for Horizon AI
 * Express server that provides HTTP endpoints for the PHP backend
 */

const app = express();
const PORT = process.env.LANGGRAPH_PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json({ limit: '10mb' }));

// Store active agent sessions
const sessions = new Map();

/**
 * Get or create an agent session
 */
function getSession(sessionId, config = {}) {
    if (!sessions.has(sessionId)) {
        const agent = new SyndicatiLangGraphAgent(
            config.baseUrl || 'http://127.0.0.1:11434',
            config.model || 'phi4-mini:3.8b'
        );
        sessions.set(sessionId, {
            agent,
            createdAt: new Date(),
            lastActivity: new Date(),
        });
    }
    
    const session = sessions.get(sessionId);
    session.lastActivity = new Date();
    return session.agent;
}

/**
 * Health check endpoint
 */
app.get('/health', async (req, res) => {
    res.json({
        status: 'ok',
        service: 'langgraph-server',
        version: '1.0.0',
        timestamp: new Date().toISOString(),
    });
});

/**
 * Check Ollama and LangGraph status
 */
app.get('/status', async (req, res) => {
    try {
        // Create temporary agent to check status
        const tempAgent = new SyndicatiLangGraphAgent();
        const status = await tempAgent.checkStatus();
        
        res.json({
            ...status,
            langgraph_server: true,
            sessions_active: sessions.size,
        });
    } catch (error) {
        res.status(500).json({
            running: false,
            error: error.message,
            langgraph_server: true,
        });
    }
});

/**
 * Initialize a new session
 */
app.post('/session/init', async (req, res) => {
    try {
        const sessionId = uuidv4();
        const { baseUrl, model } = req.body;
        
        // Pre-initialize the agent
        const agent = getSession(sessionId, { baseUrl, model });
        
        res.json({
            success: true,
            session_id: sessionId,
            message: 'Session initialized successfully',
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
        });
    }
});

/**
 * Process a message through LangGraph
 */
app.post('/chat', async (req, res) => {
    try {
        const { session_id, message, page_context, config } = req.body;
        
        if (!message) {
            return res.status(400).json({
                success: false,
                error: 'Message is required',
            });
        }
        
        // Get or create session
        const sessionId = session_id || uuidv4();
        const agent = getSession(sessionId, config);
        
        // Process through LangGraph workflow
        const result = await agent.processMessage(message, page_context);
        
        res.json({
            success: true,
            session_id: sessionId,
            ...result,
        });
    } catch (error) {
        console.error('LangGraph chat error:', error);
        res.status(500).json({
            success: false,
            error: error.message,
            reply: 'I encountered an error processing your request.',
            actions: [],
        });
    }
});

/**
 * Stream a message (for real-time updates)
 */
app.post('/chat/stream', async (req, res) => {
    try {
        const { session_id, message, page_context, config } = req.body;
        
        if (!message) {
            return res.status(400).json({
                success: false,
                error: 'Message is required',
            });
        }
        
        // Set up SSE
        res.setHeader('Content-Type', 'text/event-stream');
        res.setHeader('Cache-Control', 'no-cache');
        res.setHeader('Connection', 'keep-alive');
        
        const sessionId = session_id || uuidv4();
        const agent = getSession(sessionId, config);
        
        // Stream the workflow
        const stream = agent.streamMessage(message, page_context);
        
        res.write(`data: ${JSON.stringify({ type: 'start', session_id: sessionId })}\n\n`);
        
        for await (const update of stream) {
            res.write(`data: ${JSON.stringify({ type: 'update', ...update })}\n\n`);
        }
        
        res.write(`data: ${JSON.stringify({ type: 'end' })}\n\n`);
        res.end();
        
    } catch (error) {
        console.error('LangGraph stream error:', error);
        res.write(`data: ${JSON.stringify({ type: 'error', error: error.message })}\n\n`);
        res.end();
    }
});

/**
 * Clear session history
 */
app.post('/session/clear', async (req, res) => {
    try {
        const { session_id } = req.body;
        
        if (session_id && sessions.has(session_id)) {
            sessions.delete(session_id);
        }
        
        res.json({
            success: true,
            message: 'Session cleared',
        });
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message,
        });
    }
});

/**
 * Get active sessions (admin only)
 */
app.get('/admin/sessions', async (req, res) => {
    const sessionList = Array.from(sessions.entries()).map(([id, session]) => ({
        id,
        created_at: session.createdAt,
        last_activity: session.lastActivity,
        idle_seconds: Math.floor((Date.now() - session.lastActivity) / 1000),
    }));
    
    res.json({
        sessions: sessionList,
        total: sessions.size,
    });
});

/**
 * Cleanup old sessions periodically
 */
setInterval(() => {
    const now = Date.now();
    const maxAge = 30 * 60 * 1000; // 30 minutes
    
    for (const [id, session] of sessions.entries()) {
        if (now - session.lastActivity.getTime() > maxAge) {
            sessions.delete(id);
            console.log(`Cleaned up idle session: ${id}`);
        }
    }
}, 5 * 60 * 1000); // Run every 5 minutes

// Start server
app.listen(PORT, () => {
    console.log(`🚀 LangGraph Server running on http://localhost:${PORT}`);
    console.log(`📊 Health check: http://localhost:${PORT}/health`);
    console.log(`🔍 Status: http://localhost:${PORT}/status`);
});

export default app;
