<?php

add_action( 'wp_enqueue_scripts', 'porto_child_css', 1001 );

// Load CSS
function porto_child_css() {
	// porto child theme styles
	wp_deregister_style( 'styles-child' );
	wp_register_style( 'styles-child', esc_url( get_stylesheet_directory_uri() ) . '/style.css' );
	wp_enqueue_style( 'styles-child' );

	if ( is_rtl() ) {
		wp_deregister_style( 'styles-child-rtl' );
		wp_register_style( 'styles-child-rtl', esc_url( get_stylesheet_directory_uri() ) . '/style_rtl.css' );
		wp_enqueue_style( 'styles-child-rtl' );
	}
}

// Связка Ollama + Porto
/**
 * ГИБКАЯ ИНТЕГРАЦИЯ OLLAMA В PORTO AI
 * 
 * Все параметры вынесены в константы. 
 * Если константа не задана — используются дефолтные значения Porto.
 * 
 * КАК НАСТРАИВАТЬ:
 * Раскомментируйте нужные строки и измените значения.
 */

// ==========================================
// 🎛 НАСТРОЙКИ (раскомментируйте и измените при необходимости)
// ==========================================

// 1. МОДЕЛЬ OLLAMA
// Если не задано — используется дефолтная модель Porto (gpt-5.4-mini)
if ( ! defined( 'PORTO_AI_MODEL' ) ) {
    define( 'PORTO_AI_MODEL', 'qwen3.6:27b-coding' );
}

// 2. URL ВАШЕГО OLLAMA-СЕРВЕРА
if ( ! defined( 'PORTO_OLLAMA_URL' ) ) {
    define( 'PORTO_OLLAMA_URL', 'http://127.0.0.1:11434/v1/' );
}

// 3. ТАЙМАУТ PHP-ЗАПРОСА К OLLAMA (в секундах)
// Дефолт Porto: 30 сек. Увеличиваем, чтобы модель успела сгенерировать длинный текст.
if ( ! defined( 'PORTO_OLLAMA_TIMEOUT' ) ) {
    define( 'PORTO_OLLAMA_TIMEOUT', 120 );
}

// 4. ТАЙМАУТ JS В БРАУЗЕРЕ (в миллисекундах)
// Дефолт Porto: 35000 (35 сек). Должен быть >= PORTO_OLLAMA_TIMEOUT * 1000
if ( ! defined( 'PORTO_JS_TIMEOUT' ) ) {
    define( 'PORTO_JS_TIMEOUT', 120000 );
}

// 5. ОТКЛЮЧИТЬ "РАССУЖДЕНИЯ" МОДЕЛИ (think: false)
// qwen3.6 тратит токены на внутренние монологи. false = сразу пишет ответ.
if ( ! defined( 'PORTO_OLLAMA_DISABLE_THINKING' ) ) {
    define( 'PORTO_OLLAMA_DISABLE_THINKING', true );
}

// 6. ДОПОЛНИТЕЛЬНАЯ ИНСТРУКЦИЯ В SYSTEM PROMPT
// Добавляется к промпту Porto, чтобы модель не уходила в рассуждения
if ( ! defined( 'PORTO_OLLAMA_SYSTEM_HINT' ) ) {
    define( 'PORTO_OLLAMA_SYSTEM_HINT', 
        "\n\nCRITICAL: Do NOT use internal reasoning or thinking process. " .
        "Start writing the final text IMMEDIATELY. Return ONLY pure text."
    );
}

// 7. ПЕРЕОПРЕДЕЛЕНИЕ MAX_TOKENS (опционально)
// По умолчанию: false — используются нативные значения Porto 
// (content=2048, excerpt=64, meta_desc=265, outline=2048 и т.д.)
// Укажите число, если хотите жестко задать лимит для всех типов:
// define( 'PORTO_OLLAMA_MAX_TOKENS_OVERRIDE', 4096 );

// 8. ПЕРЕОПРЕДЕЛЕНИЕ TEMPERATURE (опционально)
// По умолчанию: false — используются нативные значения Porto
// (content=0.9, excerpt=0.1, meta_desc=0.3 и т.д.)
// Укажите число (0-2), если хотите жестко задать температуру:
// define( 'PORTO_OLLAMA_TEMPERATURE_OVERRIDE', 0.7 );


// ==========================================
// 🔧 ЛОГИКА (не трогайте, если не уверены)
// ==========================================

// 1. Подменяем модель (использует константу PORTO_AI_MODEL)
add_filter( 'porto_ai_model', function( $model ) {
    return defined( 'PORTO_AI_MODEL' ) ? PORTO_AI_MODEL : $model;
});

// 2. API-ключ (Ollama не требует ключа, но Porto проверяет его наличие)
add_filter( 'porto_ai_openai_key', function( $key ) {
    // Возвращаем заглушку, чтобы Porto считал, что ключ есть
    return 'sk-ollama-local';
});

// 3. Модифицируем тело запроса
add_filter( 'porto_ai_request_body', function( $body, $prompt ) {
    
    // 3.1. Переопределяем max_tokens, если задана константа
    if ( defined( 'PORTO_OLLAMA_MAX_TOKENS_OVERRIDE' ) ) {
        $body['max_tokens'] = PORTO_OLLAMA_MAX_TOKENS_OVERRIDE;
    }
    // Иначе — оставляем дефолт Porto (он уже в $body)
    
    // 3.2. Переопределяем temperature, если задана константа
    if ( defined( 'PORTO_OLLAMA_TEMPERATURE_OVERRIDE' ) ) {
        $body['temperature'] = PORTO_OLLAMA_TEMPERATURE_OVERRIDE;
    }
    // Иначе — оставляем дефолт Porto
    
    // 3.3. Отключаем "рассуждения" модели (если включено)
    if ( defined( 'PORTO_OLLAMA_DISABLE_THINKING' ) && PORTO_OLLAMA_DISABLE_THINKING ) {
        $body['think'] = false;
    }
    
    // 3.4. Принудительно отключаем потоковую передачу (Ollama должна вернуть JSON целиком)
    $body['stream'] = false;
    
    // 3.5. Удаляем параметры OpenAI, которые Ollama не понимает
    unset( 
        $body['response_format'], 
        $body['logprobs'], 
        $body['top_logprobs'], 
        $body['n'], 
        $body['seed'] 
    );
    
    // 3.6. Добавляем инструкцию в system prompt (если задана)
    if ( defined( 'PORTO_OLLAMA_SYSTEM_HINT' ) && PORTO_OLLAMA_SYSTEM_HINT ) {
        if ( isset( $body['messages'] ) && is_array( $body['messages'] ) ) {
            foreach ( $body['messages'] as &$msg ) {
                if ( $msg['role'] === 'system' ) {
                    $msg['content'] .= PORTO_OLLAMA_SYSTEM_HINT;
                }
            }
        }
    }
    
    return $body;
}, 10, 2);

// 4. Перехват URL и установка таймаута
add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
    if ( strpos( $url, 'api.openai.com' ) === false ) {
        return $preempt;
    }
    
    // Меняем URL на наш Ollama
    $ollama_url = str_replace( 
        'https://api.openai.com/v1/', 
        defined( 'PORTO_OLLAMA_URL' ) ? PORTO_OLLAMA_URL : 'http://127.0.0.1:11434/v1/', 
        $url 
    );
    
    // Устанавливаем таймаут из константы
    $args['timeout'] = defined( 'PORTO_OLLAMA_TIMEOUT' ) ? PORTO_OLLAMA_TIMEOUT : 120;
    $args['sslverify'] = false;
    
    return wp_remote_post( $ollama_url, $args );
}, 10, 3);

// 5. Увеличиваем JS таймаут в браузере
add_action( 'admin_footer', function() {
    if ( ! function_exists( 'get_current_screen' ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->base, array( 'post', 'page' ) ) ) return;
    
    $js_timeout = defined( 'PORTO_JS_TIMEOUT' ) ? PORTO_JS_TIMEOUT : 120000;
    ?>
    <script type="text/javascript">
    (function($) {
        if (typeof $ === 'undefined') return;
        
        var originalAjax = $.ajax;
        $.ajax = function(url, options) {
            if (typeof url === 'object') {
                options = url;
            } else {
                options = options || {};
                options.url = url;
            }
            
            // Перехватываем только запросы Porto AI
            if (options.data && typeof options.data === 'object' && options.data.action === 'porto_ai_generate') {
                options.timeout = <?php echo intval( $js_timeout ); ?>;
            }
            
            return originalAjax.call(this, options);
        };
    })(jQuery);
    </script>
    <?php
}, 999);