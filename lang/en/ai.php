<?php

return [
    'title' => 'AI Assistant', 'intro' => 'Continue an authorized conversation or start a focused request.', 'new' => 'New conversation',
    'new_conversation' => 'New conversation', 'empty' => 'No conversations yet.', 'empty_help' => 'Start a conversation to create your private AI history.',
    'model' => 'Model', 'message' => 'Message', 'message_placeholder' => 'Describe what you need…', 'additional_context' => 'Additional context',
    'send' => 'Send message', 'queued' => 'Your message is queued for processing.', 'processing' => 'Generating a response…',
    'failed' => 'The AI request could not be completed.', 'retry' => 'Retry', 'cancel' => 'Stop', 'cancelled' => 'The request was cancelled.',
    'rename' => 'Rename', 'renamed' => 'The conversation was renamed.', 'delete' => 'Delete conversation', 'deleted' => 'The conversation was deleted.',
    'delete_confirm' => 'Delete this conversation and its local history?', 'back' => 'Back to conversations', 'continue' => 'Continue conversation',
    'use_with_ai' => 'Use with AI', 'selected_prompt' => 'Selected prompt', 'prompt_context_help' => 'The saved prompt will be sent first. Add the specific context for this request.',
    'invalid_model' => 'The selected AI model is not available for your role.', 'no_models' => 'No AI models are configured for your role.',
    'local_response' => 'This is a local verification response. Configure the server-side OpenAI provider for production generation.',
    'errors' => ['not_configured' => 'AI service is not configured.', 'timeout' => 'The AI service timed out.', 'rate_limited' => 'The AI service is busy. Try again later.',
        'provider_auth' => 'AI service authentication failed.', 'provider_unavailable' => 'The AI service is temporarily unavailable.', 'provider_rejected' => 'The AI service rejected this request.',
        'empty_response' => 'The AI service returned no text.', 'access_revoked' => 'Access changed before processing.', 'cancelled_by_user' => 'Cancelled by you.',
        'conversation_deleted' => 'Conversation deleted.', 'internal_error' => 'An unexpected processing error occurred.'],
];
