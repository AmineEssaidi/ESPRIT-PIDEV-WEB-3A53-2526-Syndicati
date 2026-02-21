import { ChatOllama } from "@langchain/ollama";
import { StateGraph, END } from "@langchain/langgraph";
import { ToolNode } from "@langchain/langgraph/prebuilt";
import { MemorySaver } from "@langchain/langgraph";

/**
 * Syndicati LangGraph Agent
 * Implements a stateful multi-step reasoning workflow
 */
export class SyndicatiLangGraphAgent {
    constructor(baseUrl = 'http://127.0.0.1:11434', model = 'phi4-mini:3.8b') {
        this.llm = new ChatOllama({
            baseUrl: baseUrl,
            model: model,
            temperature: 0,
        });
        this.memory = new MemorySaver();
        this.app = this._createGraph();
    }

    _createGraph() {
        // Define the state schema
        const graphState = {
            messages: {
                value: (x, y) => x.concat(y),
                default: () => [],
            },
            intent: { value: null },
            requires_navigation: { value: false },
            destination_route: { value: null },
        };

        const workflow = new StateGraph({
            channels: graphState,
        });

        // Define nodes
        const callModel = async (state) => {
            const { messages } = state;
            const response = await this.llm.invoke(messages);
            return { messages: [response] };
        };

        // Add nodes to workflow
        workflow.addNode("agent", callModel);
        workflow.setEntryPoint("agent");
        workflow.addEdge("agent", END);

        return workflow.compile({ checkpointer: this.memory });
    }

    async checkStatus() {
        try {
            // Simple check to see if Ollama is reachable via the LLM
            await this.llm.invoke("health check");
            return { running: true, langgraph: true, model: this.llm.model };
        } catch (error) {
            return { running: false, error: error.message };
        }
    }

    async processMessage(message, pageContext = {}) {
        const initialState = {
            messages: [{ role: "user", content: message }],
        };

        const config = { configurable: { thread_id: "default" } };
        const result = await this.app.invoke(initialState, config);
        
        const lastMessage = result.messages[result.messages.length - 1];
        
        return {
            reply: lastMessage.content,
            actions: [],
            intent: result.intent,
            steps: result.messages.length,
        };
    }

    async *streamMessage(message, pageContext = {}) {
        const initialState = {
            messages: [{ role: "user", content: message }],
        };

        const config = { configurable: { thread_id: "default" } };
        const stream = await this.app.stream(initialState, config);

        for await (const chunk of stream) {
            yield chunk;
        }
    }
}
