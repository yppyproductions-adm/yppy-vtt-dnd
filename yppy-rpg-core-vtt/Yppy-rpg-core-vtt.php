<?php
/*
Plugin Name: Yppy RPG Core + VTT
Description: Core de campanhas, mesas, personagens, cenários e um VTT 3D simples para Yppy.
Version: 0.1.0
Author: Alessandra & IA
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'YPRPG_CORE_VTT_VERSION', '0.1.0' );
define( 'YPRPG_CORE_VTT_DIR', plugin_dir_path( __FILE__ ) );
define( 'YPRPG_CORE_VTT_URL', plugin_dir_url( __FILE__ ) );

/**
 * 1) REGISTRO DE CPTs E TAXONOMIAS
 */
add_action( 'init', 'yprpg_register_cpts_and_taxonomies' );
function yprpg_register_cpts_and_taxonomies() {

    // ----- TAXONOMIAS -----

    // Sistema de jogo (DnD 5e, Yppy etc.)
    register_taxonomy(
        'yprpg_sistema',
        array( 'yprpg_campanha', 'yprpg_personagem', 'yprpg_cenario' ),
        array(
            'label'        => 'Sistema de Jogo',
            'public'       => true,
            'hierarchical' => false,
            'show_ui'      => true,
            'show_in_menu' => true,
        )
    );

    // Tema / Bioma (Cidade, Floresta etc.)
    register_taxonomy(
        'yprpg_tema',
        array( 'yprpg_cenario', 'yprpg_campanha' ),
        array(
            'label'        => 'Tema / Bioma',
            'public'       => true,
            'hierarchical' => true,
            'show_ui'      => true,
            'show_in_menu' => true,
        )
    );

    // ----- CPT CAMPANHA -----
    register_post_type(
        'yprpg_campanha',
        array(
            'label'           => 'Campanhas',
            'public'          => true,
            'has_archive'     => true,
            'show_in_menu'    => true,
            'menu_position'   => 25,
            'menu_icon'       => 'dashicons-book',
            'supports'        => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'    => true,
        )
    );

    // ----- CPT MESA -----
    register_post_type(
        'yprpg_mesa',
        array(
            'label'           => 'Mesas',
            'public'          => true,
            'has_archive'     => true,
            'show_in_menu'    => true,
            'menu_position'   => 26,
            'menu_icon'       => 'dashicons-groups',
            'supports'        => array( 'title' ),
            'show_in_rest'    => true,
        )
    );

    // ----- CPT PERSONAGEM -----
    register_post_type(
        'yprpg_personagem',
        array(
            'label'           => 'Personagens',
            'public'          => true,
            'has_archive'     => true,
            'show_in_menu'    => true,
            'menu_position'   => 27,
            'menu_icon'       => 'dashicons-universal-access',
            'supports'        => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'    => true,
        )
    );

    // ----- CPT CENÁRIO -----
    register_post_type(
        'yprpg_cenario',
        array(
            'label'           => 'Cenários',
            'public'          => true,
            'has_archive'     => true,
            'show_in_menu'    => true,
            'menu_position'   => 28,
            'menu_icon'       => 'dashicons-location-alt',
            'supports'        => array( 'title', 'editor', 'thumbnail' ),
            'show_in_rest'    => true,
        )
    );
}

/**
 * 2) META BOXES PARA CAMPANHA, MESA, PERSONAGEM, CENÁRIO
 */
add_action( 'add_meta_boxes', 'yprpg_add_meta_boxes' );
function yprpg_add_meta_boxes() {

    // CAMPANHA: Mestre responsável
    add_meta_box(
        'yprpg_campanha_mestre',
        'Mestre da Campanha',
        'yprpg_campanha_mestre_metabox',
        'yprpg_campanha',
        'side',
        'default'
    );

    // MESA: Campanha, Cenário, Jogadores
    add_meta_box(
        'yprpg_mesa_dados',
        'Dados da Mesa',
        'yprpg_mesa_dados_metabox',
        'yprpg_mesa',
        'normal',
        'default'
    );

    // PERSONAGEM: Dono, Tipo, Resumo da Ficha
    add_meta_box(
        'yprpg_personagem_dados',
        'Dados do Personagem',
        'yprpg_personagem_dados_metabox',
        'yprpg_personagem',
        'normal',
        'default'
    );

    // CENÁRIO: Grid
    add_meta_box(
        'yprpg_cenario_grid',
        'Configuração de Grid',
        'yprpg_cenario_grid_metabox',
        'yprpg_cenario',
        'side',
        'default'
    );
}

// ----- Metabox Campanha -----
function yprpg_campanha_mestre_metabox( $post ) {
    wp_nonce_field( 'yprpg_save_campanha', 'yprpg_campanha_nonce' );
    $mestre_id = get_post_meta( $post->ID, '_yprpg_mestre_id', true );
    $users     = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
    ?>
    <p><label for="yprpg_mestre_id">Selecione o mestre:</label></p>
    <select name="yprpg_mestre_id" id="yprpg_mestre_id" style="width:100%;">
        <option value="">— Nenhum —</option>
        <?php foreach ( $users as $user ) : ?>
            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $mestre_id, $user->ID ); ?>>
                <?php echo esc_html( $user->display_name . ' (ID ' . $user->ID . ')' ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// ----- Metabox Mesa -----
function yprpg_mesa_dados_metabox( $post ) {
    wp_nonce_field( 'yprpg_save_mesa', 'yprpg_mesa_nonce' );

    $campanha_id = get_post_meta( $post->ID, '_yprpg_campanha_id', true );
    $cenario_id  = get_post_meta( $post->ID, '_yprpg_cenario_id', true );
    $jogadores   = get_post_meta( $post->ID, '_yprpg_jogadores_ids', true );
    if ( ! is_array( $jogadores ) ) {
        $jogadores = array();
    }

    $campanhas = get_posts( array(
        'post_type'      => 'yprpg_campanha',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );
    $cenarios = get_posts( array(
        'post_type'      => 'yprpg_cenario',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ) );
    $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
    ?>
    <p>
        <label for="yprpg_campanha_id"><strong>Campanha:</strong></label><br/>
        <select name="yprpg_campanha_id" id="yprpg_campanha_id" style="width:100%;">
            <option value="">— Nenhuma —</option>
            <?php foreach ( $campanhas as $c ) : ?>
                <option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $campanha_id, $c->ID ); ?>>
                    <?php echo esc_html( $c->post_title . ' (ID ' . $c->ID . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="yprpg_cenario_id"><strong>Cenário:</strong></label><br/>
        <select name="yprpg_cenario_id" id="yprpg_cenario_id" style="width:100%;">
            <option value="">— Nenhum —</option>
            <?php foreach ( $cenarios as $c ) : ?>
                <option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $cenario_id, $c->ID ); ?>>
                    <?php echo esc_html( $c->post_title . ' (ID ' . $c->ID . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="yprpg_jogadores_ids"><strong>Jogadores (usuários):</strong></label><br/>
        <select name="yprpg_jogadores_ids[]" id="yprpg_jogadores_ids" multiple style="width:100%;min-height:120px;">
            <?php foreach ( $users as $user ) : ?>
                <option value="<?php echo esc_attr( $user->ID ); ?>"
                    <?php echo in_array( (string) $user->ID, array_map( 'strval', $jogadores ), true ) ? 'selected' : ''; ?>>
                    <?php echo esc_html( $user->display_name . ' (ID ' . $user->ID . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small>Use CTRL/CMD para selecionar vários.</small>
    </p>
    <?php
}

// ----- Metabox Personagem -----
function yprpg_personagem_dados_metabox( $post ) {
    wp_nonce_field( 'yprpg_save_personagem', 'yprpg_personagem_nonce' );
    $dono_id   = get_post_meta( $post->ID, '_yprpg_dono_id', true );
    $tipo      = get_post_meta( $post->ID, '_yprpg_tipo', true );
    $nivel     = get_post_meta( $post->ID, '_yprpg_nivel', true );
    $hp_atual  = get_post_meta( $post->ID, '_yprpg_hp_atual', true );
    $hp_max    = get_post_meta( $post->ID, '_yprpg_hp_max', true );
    $ca        = get_post_meta( $post->ID, '_yprpg_ca', true );
    $ficha_raw = get_post_meta( $post->ID, '_yprpg_ficha_json', true );

    $users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );
    ?>
    <p>
        <label for="yprpg_dono_id"><strong>Dono (usuário):</strong></label><br/>
        <select name="yprpg_dono_id" id="yprpg_dono_id" style="width:100%;">
            <option value="">— Nenhum —</option>
            <?php foreach ( $users as $user ) : ?>
                <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $dono_id, $user->ID ); ?>>
                    <?php echo esc_html( $user->display_name . ' (ID ' . $user->ID . ')' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <strong>Tipo:</strong><br/>
        <label>
            <input type="radio" name="yprpg_tipo" value="jogador" <?php checked( $tipo, 'jogador' ); ?> /> Jogador
        </label><br/>
        <label>
            <input type="radio" name="yprpg_tipo" value="npc" <?php checked( $tipo, 'npc' ); ?> /> NPC
        </label>
    </p>
    <p>
        <label for="yprpg_nivel"><strong>Nível:</strong></label><br/>
        <input type="number" name="yprpg_nivel" id="yprpg_nivel" value="<?php echo esc_attr( $nivel ); ?>" min="1" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_hp_max"><strong>HP Máximo:</strong></label><br/>
        <input type="number" name="yprpg_hp_max" id="yprpg_hp_max" value="<?php echo esc_attr( $hp_max ); ?>" min="0" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_hp_atual"><strong>HP Atual:</strong></label><br/>
        <input type="number" name="yprpg_hp_atual" id="yprpg_hp_atual" value="<?php echo esc_attr( $hp_atual ); ?>" min="0" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_ca"><strong>Classe de Armadura (CA):</strong></label><br/>
        <input type="number" name="yprpg_ca" id="yprpg_ca" value="<?php echo esc_attr( $ca ); ?>" min="0" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_ficha_json"><strong>Ficha (JSON simples, opcional):</strong></label><br/>
        <textarea name="yprpg_ficha_json" id="yprpg_ficha_json" rows="5" style="width:100%;"><?php echo esc_textarea( $ficha_raw ); ?></textarea>
        <small>Opcional. Pode deixar vazio por enquanto.</small>
    </p>
    <?php
}

// ----- Metabox CENÁRIO -----
function yprpg_cenario_grid_metabox( $post ) {
    wp_nonce_field( 'yprpg_save_cenario', 'yprpg_cenario_nonce' );
    $largura = get_post_meta( $post->ID, '_yprpg_grid_largura', true );
    $altura  = get_post_meta( $post->ID, '_yprpg_grid_altura', true );
    $altmax  = get_post_meta( $post->ID, '_yprpg_altura_maxima', true );

    if ( '' === $largura ) {
        $largura = 20;
    }
    if ( '' === $altura ) {
        $altura = 20;
    }
    if ( '' === $altmax ) {
        $altmax = 3;
    }
    ?>
    <p>
        <label for="yprpg_grid_largura"><strong>Largura (casas):</strong></label><br/>
        <input type="number" name="yprpg_grid_largura" id="yprpg_grid_largura" value="<?php echo esc_attr( $largura ); ?>" min="1" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_grid_altura"><strong>Altura (casas):</strong></label><br/>
        <input type="number" name="yprpg_grid_altura" id="yprpg_grid_altura" value="<?php echo esc_attr( $altura ); ?>" min="1" style="width:100%;"/>
    </p>
    <p>
        <label for="yprpg_altura_maxima"><strong>Altura máxima (níveis de voo):</strong></label><br/>
        <input type="number" name="yprpg_altura_maxima" id="yprpg_altura_maxima" value="<?php echo esc_attr( $altmax ); ?>" min="1" style="width:100%;"/>
    </p>
    <?php
}

/**
 * 3) SALVAR METAS
 */
add_action( 'save_post_yprpg_campanha', 'yprpg_save_campanha_meta' );
function yprpg_save_campanha_meta( $post_id ) {
    if ( ! isset( $_POST['yprpg_campanha_nonce'] ) || ! wp_verify_nonce( $_POST['yprpg_campanha_nonce'], 'yprpg_save_campanha' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['yprpg_mestre_id'] ) ) {
        update_post_meta( $post_id, '_yprpg_mestre_id', (int) $_POST['yprpg_mestre_id'] );
    }
}

add_action( 'save_post_yprpg_mesa', 'yprpg_save_mesa_meta' );
function yprpg_save_mesa_meta( $post_id ) {
    if ( ! isset( $_POST['yprpg_mesa_nonce'] ) || ! wp_verify_nonce( $_POST['yprpg_mesa_nonce'], 'yprpg_save_mesa' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( isset( $_POST['yprpg_campanha_id'] ) ) {
        update_post_meta( $post_id, '_yprpg_campanha_id', (int) $_POST['yprpg_campanha_id'] );
    }

    if ( isset( $_POST['yprpg_cenario_id'] ) ) {
        update_post_meta( $post_id, '_yprpg_cenario_id', (int) $_POST['yprpg_cenario_id'] );
    }

    if ( isset( $_POST['yprpg_jogadores_ids'] ) && is_array( $_POST['yprpg_jogadores_ids'] ) ) {
        $ids = array_map( 'intval', $_POST['yprpg_jogadores_ids'] );
        update_post_meta( $post_id, '_yprpg_jogadores_ids', $ids );
    } else {
        delete_post_meta( $post_id, '_yprpg_jogadores_ids' );
    }
}

add_action( 'save_post_yprpg_personagem', 'yprpg_save_personagem_meta' );
function yprpg_save_personagem_meta( $post_id ) {
    if ( ! isset( $_POST['yprpg_personagem_nonce'] ) || ! wp_verify_nonce( $_POST['yprpg_personagem_nonce'], 'yprpg_save_personagem' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $dono_id = isset( $_POST['yprpg_dono_id'] ) ? (int) $_POST['yprpg_dono_id'] : '';
    $tipo    = isset( $_POST['yprpg_tipo'] ) ? sanitize_text_field( $_POST['yprpg_tipo'] ) : '';
    $nivel   = isset( $_POST['yprpg_nivel'] ) ? (int) $_POST['yprpg_nivel'] : '';
    $hp_max  = isset( $_POST['yprpg_hp_max'] ) ? (int) $_POST['yprpg_hp_max'] : '';
    $hp_at   = isset( $_POST['yprpg_hp_atual'] ) ? (int) $_POST['yprpg_hp_atual'] : '';
    $ca      = isset( $_POST['yprpg_ca'] ) ? (int) $_POST['yprpg_ca'] : '';
    $ficha   = isset( $_POST['yprpg_ficha_json'] ) ? wp_kses_post( $_POST['yprpg_ficha_json'] ) : '';

    update_post_meta( $post_id, '_yprpg_dono_id', $dono_id );
    update_post_meta( $post_id, '_yprpg_tipo', $tipo );
    update_post_meta( $post_id, '_yprpg_nivel', $nivel );
    update_post_meta( $post_id, '_yprpg_hp_max', $hp_max );
    update_post_meta( $post_id, '_yprpg_hp_atual', $hp_at );
    update_post_meta( $post_id, '_yprpg_ca', $ca );
    update_post_meta( $post_id, '_yprpg_ficha_json', $ficha );
}

add_action( 'save_post_yprpg_cenario', 'yprpg_save_cenario_meta' );
function yprpg_save_cenario_meta( $post_id ) {
    if ( ! isset( $_POST['yprpg_cenario_nonce'] ) || ! wp_verify_nonce( $_POST['yprpg_cenario_nonce'], 'yprpg_save_cenario' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $largura = isset( $_POST['yprpg_grid_largura'] ) ? (int) $_POST['yprpg_grid_largura'] : 20;
    $altura  = isset( $_POST['yprpg_grid_altura'] ) ? (int) $_POST['yprpg_grid_altura'] : 20;
    $altmax  = isset( $_POST['yprpg_altura_maxima'] ) ? (int) $_POST['yprpg_altura_maxima'] : 3;

    update_post_meta( $post_id, '_yprpg_grid_largura', $largura );
    update_post_meta( $post_id, '_yprpg_grid_altura', $altura );
    update_post_meta( $post_id, '_yprpg_altura_maxima', $altmax );
}

/**
 * 4) SHORTCODE DO VTT
 * Uso: [yppy_vtt mesa="123"]
 */
add_shortcode( 'yppy_vtt', 'yprpg_shortcode_vtt' );
function yprpg_shortcode_vtt( $atts ) {
    $atts = shortcode_atts(
        array(
            'mesa' => '',
        ),
        $atts,
        'yppy_vtt'
    );
    $mesa_id = (int) $atts['mesa'];

    if ( ! $mesa_id ) {
        return '<p><strong>Yppy VTT:</strong> nenhuma mesa definida. Use [yppy_vtt mesa="ID_DA_MESA"].</p>';
    }

    // Buscar cenário e grid
    $cenario_id = (int) get_post_meta( $mesa_id, '_yprpg_cenario_id', true );
    if ( ! $cenario_id ) {
        // Só pra evitar erro
        $largura = 20;
        $altura  = 20;
        $altmax  = 3;
    } else {
        $largura = (int) get_post_meta( $cenario_id, '_yprpg_grid_largura', true );
        $altura  = (int) get_post_meta( $cenario_id, '_yprpg_grid_altura', true );
        $altmax  = (int) get_post_meta( $cenario_id, '_yprpg_altura_maxima', true );
        if ( ! $largura ) { $largura = 20; }
        if ( ! $altura )  { $altura  = 20; }
        if ( ! $altmax )  { $altmax  = 3; }
    }

    // Enfileirar scripts
    yprpg_enqueue_vtt_scripts( $mesa_id, $largura, $altura, $altmax );

    ob_start();
    ?>
    <div class="yprpg-vtt-wrapper">
        <div id="yprpg-vtt-canvas" 
             data-mesa-id="<?php echo esc_attr( $mesa_id ); ?>"
             style="width:100%;height:500px;border:1px solid #444;background:#111;">
        </div>
        <div class="yprpg-vtt-ui" style="margin-top:8px;">
            <button type="button" id="yprpg-alt-up">Altitude +</button>
            <button type="button" id="yprpg-alt-down">Altitude -</button>
            <span style="margin-left:10px;">Clique em uma bolinha para selecionar e clique no grid para mover.</span>
        </div>
        <div id="yprpg-vtt-log" style="margin-top:8px;font-size:13px;background:#222;color:#eee;padding:6px;max-height:120px;overflow:auto;">
            Log da mesa...
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 5) ENQUEUE SCRIPTS + LOCALIZE
 */
function yprpg_enqueue_vtt_scripts( $mesa_id, $largura, $altura, $altmax ) {

    // Three.js local (você precisa colocar o arquivo assets/js/three.min.js)
    wp_register_script(
        'yprpg-threejs',
        YPRPG_CORE_VTT_URL . 'assets/js/three.min.js',
        array(),
        '0.155.0',
        true
    );

    wp_register_script(
        'yprpg-vtt',
        YPRPG_CORE_VTT_URL . 'assets/js/yprpg-vtt.js',
        array( 'yprpg-threejs' ),
        YPRPG_CORE_VTT_VERSION,
        true
    );

    wp_enqueue_script( 'yprpg-threejs' );
    wp_enqueue_script( 'yprpg-vtt' );

    $data = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'yprpg_vtt_nonce' ),
        'mesa_id'  => $mesa_id,
        'grid'     => array(
            'largura' => $largura,
            'altura'  => $altura,
            'altmax'  => $altmax,
        ),
    );

    wp_localize_script( 'yprpg-vtt', 'YPRPG_VTT_DATA', $data );
}

/**
 * 6) AJAX: BUSCAR E SALVAR ESTADO DA MESA
 * Estado simples: lista de tokens com posição e altitude
 */

// Buscar estado
add_action( 'wp_ajax_yprpg_get_mesa_state', 'yprpg_get_mesa_state' );
add_action( 'wp_ajax_nopriv_yprpg_get_mesa_state', 'yprpg_get_mesa_state' );
function yprpg_get_mesa_state() {
    check_ajax_referer( 'yprpg_vtt_nonce', 'nonce' );

    $mesa_id = isset( $_POST['mesa_id'] ) ? (int) $_POST['mesa_id'] : 0;
    if ( ! $mesa_id ) {
        wp_send_json_error( array( 'message' => 'Mesa inválida.' ) );
    }

    $state_raw = get_post_meta( $mesa_id, '_yprpg_vtt_state', true );
    if ( empty( $state_raw ) ) {
        // estado inicial: 3 tokens de teste
        $state = array(
            'tokens' => array(
                array( 'id' => 't1', 'x' => 0,  'y' => 0, 'z' => 0 ),
                array( 'id' => 't2', 'x' => 2,  'y' => 0, 'z' => 2 ),
                array( 'id' => 't3', 'x' => -2, 'y' => 0, 'z' => -2 ),
            ),
        );
    } else {
        $state = json_decode( $state_raw, true );
        if ( ! is_array( $state ) ) {
            $state = array(
                'tokens' => array(),
            );
        }
    }

    wp_send_json_success( $state );
}

// Salvar estado
add_action( '