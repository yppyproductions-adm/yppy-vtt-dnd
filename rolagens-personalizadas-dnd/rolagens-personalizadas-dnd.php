<?php
/*
Plugin Name: Rolagens personalizadas DnD
Description: Ferramenta para rolar ataques em massa personalizados para DnD (inimigos, grupos de criaturas, personagens, vantagem, dano, críticos, paralisia etc.).
Version: 0.2.0
Author: Alessandra & IA
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rolagens_Personalizadas_DnD {

    public function __construct() {
        add_shortcode( 'rolagens_dnd', array( $this, 'shortcode_rolagens_dnd' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_register_script(
            'rolagens-dnd-js',
            plugin_dir_url( __FILE__ ) . 'rolagens-dnd.js',
            array(),
            '0.2.0',
            true
        );
    }

    public function shortcode_rolagens_dnd( $atts ) {
        wp_enqueue_script( 'rolagens-dnd-js' );

        $id = 'rolagens-dnd-' . wp_generate_uuid4();

        ob_start();
        ?>
<div class="rolagens-dnd-widget" id="<?php echo esc_attr( $id ); ?>">
    <h3>Rolagens personalizadas DnD</h3>

    <!-- INIMIGOS -->
    <div class="rolagens-dnd-section">
        <h4>Inimigos</h4>
        <p>Defina os inimigos deste combate (nome, CA e se estão paralisados).</p>

        <table class="rolagens-dnd-table rolagens-dnd-enemies">
            <thead>
                <tr>
                    <th>Nome do inimigo</th>
                    <th>CA</th>
                    <th>Paralisado?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="enemy-name" value="Inimigo 1">
                    </td>
                    <td>
                        <input type="number" class="enemy-ca" value="15" min="1" max="40">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="enemy-paralyzed">
                    </td>
                    <td>
                        <button type="button" class="remove-enemy">X</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="add-enemy">+ Adicionar inimigo</button>
    </div>

    <!-- GRUPOS -->
    <div class="rolagens-dnd-section">
        <h4>Grupos de criaturas / ataques</h4>
        <p>Cada grupo representa várias criaturas atacando o mesmo inimigo com as mesmas regras.</p>

        <table class="rolagens-dnd-table rolagens-dnd-groups">
            <thead>
                <tr>
                    <th>Nome do grupo</th>
                    <th>Inimigo alvo</th>
                    <th>Qtd. criaturas</th>
                    <th>Ataques por criatura</th>
                    <th>Rolagem de acerto<br><small>(ex.: 1d20+5)</small></th>
                    <th>Rolagem de dano<br><small>(ex.: 1d6+3 ou 1d4+1d8+3)</small></th>
                    <th>Vantagem?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="group-name" value="Esqueletos flanqueando">
                    </td>
                    <td>
                        <select class="group-enemy">
                            <option value="">Selecione...</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="group-creatures" value="4" min="1">
                    </td>
                    <td>
                        <input type="number" class="group-attacks" value="1" min="1">
                    </td>
                    <td>
                        <input type="text" class="group-attack-roll" value="1d20+5">
                    </td>
                    <td>
                        <input type="text" class="group-damage-roll" value="1d6+3">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="group-advantage" checked>
                    </td>
                    <td>
                        <button type="button" class="remove-group">X</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="add-group">+ Adicionar grupo</button>
    </div>

    <!-- PERSONAGENS -->
    <div class="rolagens-dnd-section">
        <h4>Personagens (ataques individuais)</h4>
        <p>Use esta área para rolar ataques de um único personagem por turno.</p>

        <table class="rolagens-dnd-table rolagens-dnd-players">
            <thead>
                <tr>
                    <th>Nome do personagem</th>
                    <th>Inimigo alvo</th>
                    <th>Ataques por turno</th>
                    <th>Rolagem de acerto<br><small>(ex.: 1d20+7)</small></th>
                    <th>Rolagem de dano<br><small>(ex.: 1d6+1d8+3)</small></th>
                    <th>Vantagem?</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <input type="text" class="player-name" value="Personagem 1">
                    </td>
                    <td>
                        <select class="player-enemy">
                            <option value="">Selecione...</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="player-attacks" value="2" min="1">
                    </td>
                    <td>
                        <input type="text" class="player-attack-roll" value="1d20+7">
                    </td>
                    <td>
                        <input type="text" class="player-damage-roll" value="1d6+1d8+3">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="player-advantage">
                    </td>
                    <td>
                        <button type="button" class="remove-player">X</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="add-player">+ Adicionar personagem</button>
    </div>

    <!-- BOTÃO ROLAR -->
    <div class="rolagens-dnd-section">
        <button type="button" class="run-rolls">Rolar turno</button>
    </div>

    <!-- RESULTADOS -->
    <div class="rolagens-dnd-results"></div>

    <!-- CALCULADORA DE DANO -->
    <div class="rolagens-dnd-calculator">
        <div class="rolagens-dnd-calculator-wrapper">
            <img src="https://lightgreen-wren-814745.hostingersite.com/wp-content/uploads/2025/11/calculator-1870480_1280.png"
     alt="Calculadora de dano" class="rolagens-dnd-calculator-img">
            <div class="rolagens-dnd-calculator-screen">0</div>
        </div>
    </div>
</div>

<style>
.rolagens-dnd-widget {
    border: 1px solid #ddd;
    padding: 12px;
    margin: 16px 0;
    border-radius: 8px;
    background: #fafafa;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    max-width: 100%;
}
.rolagens-dnd-section {
    margin-bottom: 16px;
}
.rolagens-dnd-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
    font-size: 0.9rem;
}
.rolagens-dnd-table th,
.rolagens-dnd-table td {
    border: 1px solid #ddd;
    padding: 4px 6px;
}
.rolagens-dnd-table th {
    background: #eee;
}
.rolagens-dnd-table input,
.rolagens-dnd-table select {
    width: 100%;
    box-sizing: border-box;
    font-size: 0.9rem;
}
.rolagens-dnd-section button {
    padding: 6px 10px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}
.run-rolls {
    background: #222;
    color: #fff;
    font-weight: 600;
}
.add-enemy,
.add-group,
.add-player {
    background: #007bff; /* Azul padrão */
    color: #fff;
    transition: background 180ms ease;
}
.add-enemy:hover,
.add-group:hover,
.add-player:hover {
    background: #c084fc; /* Lilás no hover */
}
.remove-enemy,
.remove-group,
.remove-player {
    background: #b00020;
    color: #fff;
    padding: 4px 8px;
}
.rolagens-dnd-results {
    margin-top: 16px;
    font-size: 0.9rem;
}
.enemy-block {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 10px;
    background: #fff;
}
.enemy-block h4 {
    margin: 0 0 4px 0;
    font-size: 1rem;
}
.enemy-summary {
    font-size: 0.85rem;
    margin-bottom: 6px;
}
.attack-result {
    margin-bottom: 4px;
}
.attack-result-hit {
    color: #006400;
}
.attack-result-miss {
    color: #b00020;
}
.attack-meta {
    font-size: 0.8rem;
    color: #555;
}
.total-damage-summary {
    margin-top: 8px;
    font-weight: 600;
}

/* CALCULADORA */
.rolagens-dnd-calculator {
    margin-top: 16px;
    text-align: center;
}
.rolagens-dnd-calculator-wrapper {
    position: relative;
    display: inline-block;
    max-width: 280px;
    width: 100%;
}
.rolagens-dnd-calculator-img {
    width: 100%;
    height: auto;
    display: block;
}
.rolagens-dnd-calculator-screen {
    position: absolute;
    top: 12%;
    left: 14%;
    right: 14%;
    height: 13%;
    background: rgba(0, 0, 0, 0.75);
    color: #0f0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 4px 8px;
    box-sizing: border-box;
    font-family: "Courier New", monospace;
    font-size: 1.2rem;
}
/* --- AJUSTES VISUAIS DA ÁREA DE RESULTADOS --- */

.rolagens-dnd-results {
    margin-top: 24px;
    padding: 16px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
}

/* bloco de cada inimigo */
.enemy-block {
    background: #ffffff;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 12px 14px;
    margin-bottom: 18px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* título do inimigo */
.enemy-block h4 {
    margin: 0 0 6px 0;
    font-size: 1.1rem;
    font-weight: 600;
}

/* linha de resumo */
.enemy-summary {
    background: #f0f0f0;
    padding: 6px 8px;
    border-radius: 6px;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

/* ataques */
.attack-result {
    padding: 4px 0;
    font-size: 0.9rem;
    line-height: 1.25rem;
    border-bottom: 1px dashed #ddd;
}
.attack-result:last-child {
    border-bottom: none;
}

/* acerto / erro */
.attack-result-hit {
    color: #008000;
    font-weight: 600;
}
.attack-result-miss {
    color: #b00020;
    font-weight: 600;
}

/* metadados */
.attack-meta {
    font-size: 0.8rem;
    color: #555;
}

/* dano total causado */
.total-damage-summary {
    margin-top: 12px;
    font-size: 1rem;
    font-weight: 700;
    text-align: right;
    padding-top: 8px;
    border-top: 2px solid #ccc;
}

/* distanciamento do bloco de resultados para a calculadora */
.rolagens-dnd-calculator {
    margin-top: 30px !important;
}

</style>
        <?php
        return ob_get_clean();
    }
}

new Rolagens_Personalizadas_DnD();
