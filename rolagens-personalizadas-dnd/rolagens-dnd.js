(function () {
    console.log('rolagens-dnd.js loaded — aligning calculator screen');
    // ---------- Utilidades de dados ----------

    function rollDie(faces) {
        return Math.floor(Math.random() * faces) + 1;
    }

    // Notação simples de ataque: "XdY+Z" (normalmente "1d20+5")
    function parseDieNotation(notation) {
        if (!notation) return null;
        notation = notation.replace(/\s+/g, '').toLowerCase();
        var regex = /^(\d*)d(\d+)([+\-]\d+)?$/;
        var match = notation.match(regex);
        if (!match) return null;

        var count = match[1] ? parseInt(match[1], 10) : 1;
        var faces = parseInt(match[2], 10);
        var modifier = match[3] ? parseInt(match[3], 10) : 0;

        if (!faces || !count) return null;

        return {
            count: count,
            faces: faces,
            modifier: modifier
        };
    }

    // Expressão de dano: aceita múltiplos dados e modificadores, ex.:
    // "1d6+3", "1d4+1d8+3", "2d6-1", "1d6+2d4-3"
    function parseDamageExpression(expr) {
        if (!expr) return null;
        expr = expr.replace(/\s+/g, '').toLowerCase();
        if (!expr) return null;

        var tokens = [];
        var buffer = '';

        for (var i = 0; i < expr.length; i++) {
            var ch = expr[i];
            if (ch === '+' || ch === '-') {
                if (buffer.length > 0) {
                    tokens.push(buffer);
                }
                buffer = ch;
            } else {
                buffer += ch;
            }
        }
        if (buffer.length > 0) {
            tokens.push(buffer);
        }

        if (!tokens.length) return null;

        var parts = [];

        tokens.forEach(function (tok) {
            var sign = 1;
            if (tok[0] === '+' || tok[0] === '-') {
                if (tok[0] === '-') sign = -1;
                tok = tok.slice(1);
            }
            if (!tok) return;

            var dpos = tok.indexOf('d');
            if (dpos !== -1) {
                var countStr = tok.substring(0, dpos) || '1';
                var facesStr = tok.substring(dpos + 1);
                var count = parseInt(countStr, 10);
                var faces = parseInt(facesStr, 10);
                if (isNaN(count) || isNaN(faces)) return;
                parts.push({
                    type: 'dice',
                    count: sign * count,
                    faces: faces
                });
            } else {
                var val = parseInt(tok, 10);
                if (isNaN(val)) return;
                parts.push({
                    type: 'flat',
                    value: sign * val
                });
            }
        });

        if (!parts.length) return null;
        return parts;
    }

    // Rola dano com suporte a múltiplos dados e crítico (dobra apenas dados)
    function rollDamage(notation, isCrit) {
        var parts = parseDamageExpression(notation);
        if (!parts) {
            return { error: 'Notação de dano inválida: ' + notation };
        }

        var rolls = [];
        var flat = 0;

        parts.forEach(function (part) {
            if (part.type === 'dice') {
                var baseCount = Math.abs(part.count);
                var sign = part.count >= 0 ? 1 : -1;
                var totalCount = baseCount * (isCrit ? 2 : 1);

                for (var i = 0; i < totalCount; i++) {
                    var r = rollDie(part.faces);
                    rolls.push(sign * r);
                }
            } else if (part.type === 'flat') {
                flat += part.value;
            }
        });

        var sumDice = rolls.reduce(function (acc, v) { return acc + v; }, 0);
        var total = sumDice + flat;

        return {
            notation: notation,
            rolls: rolls,
            flat: flat,
            total: total,
            crit: !!isCrit
        };
    }

    // Rola ataque: suporta vantagem, CA do inimigo e paralisia
    // isParalyzed => acerto automático + crítico automático
    function rollAttack(attackNotation, advantageType, enemyCA, isParalyzed) {
        var parsed = parseDieNotation(attackNotation);
        if (!parsed) {
            return { error: 'Notação de ataque inválida: ' + attackNotation };
        }

        var faces = parsed.faces; // em DnD normalmente 20
        var bonus = parsed.modifier;

        var d1 = rollDie(faces);
        var d2 = null;
        var chosen = d1;

        if (advantageType === 'adv' || advantageType === 'dis') {
            d2 = rollDie(faces);
            if (advantageType === 'adv') {
                chosen = Math.max(d1, d2);
            } else {
                chosen = Math.min(d1, d2);
            }
        }

        var totalAttack = chosen + bonus;
        var isCrit = false;
        var hit = null;

        if (isParalyzed) {
            // Alvo paralisado: consideramos acerto e crítico automático
            hit = true;
            isCrit = true;
        } else {
            // Crítico: valor máximo do dado (ex.: 20 num d20)
            if (chosen === faces) {
                isCrit = true;
                hit = true;
            } else if (typeof enemyCA === 'number') {
                hit = totalAttack >= enemyCA;
            }
        }

        return {
            d1: d1,
            d2: d2,
            chosen: chosen,
            bonus: bonus,
            totalAttack: totalAttack,
            enemyCA: enemyCA,
            hit: hit,
            isCrit: isCrit,
            faces: faces
        };
    }

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ---------- Lógica do widget ----------

    function initWidget(widget) {
        var enemiesTableBody = widget.querySelector('.rolagens-dnd-enemies tbody');
        var groupsTableBody = widget.querySelector('.rolagens-dnd-groups tbody');
        var playersTableBody = widget.querySelector('.rolagens-dnd-players tbody');

        var addEnemyBtn = widget.querySelector('.add-enemy');
        var addGroupBtn = widget.querySelector('.add-group');
        var addPlayerBtn = widget.querySelector('.add-player');
        var runRollsBtn = widget.querySelector('.run-rolls');
        var resultsContainer = widget.querySelector('.rolagens-dnd-results');

        // --- Ajuste dinâmico do visor da calculadora ---
        var calcWrapper = widget.querySelector('.rolagens-dnd-calculator-wrapper');
        var calcImg = widget.querySelector('.rolagens-dnd-calculator-img');
        var calcScreen = widget.querySelector('.rolagens-dnd-calculator-screen');

        function alignCalculatorScreen() {
            if (!calcWrapper || !calcImg || !calcScreen) return;

            // Medir a imagem real dentro do wrapper
            var imgRect = calcImg.getBoundingClientRect();
            var wrapRect = calcWrapper.getBoundingClientRect();

            // coordenadas relativas ao wrapper
            var relLeft = imgRect.left - wrapRect.left;
            var relTop = imgRect.top - wrapRect.top;
            var imgW = imgRect.width;
            var imgH = imgRect.height;

            // Ratios calibradas para o PNG (ajuste fino possível se necessário)
            // Ajuste iterativo: mover visor mais para a direita e reduzir largura/altura
            var leftRatio = 0.165;   // margem esquerda do visor dentro da imagem (aumentado para deslocar à direita)
            var topRatio = 0.09;     // distância do topo da imagem até o visor (pequeno ajuste)
            var widthRatio = 0.70;   // largura do visor em relação à largura da imagem (reduzida para caber)
            var heightRatio = 0.14;  // altura do visor em relação à altura da imagem (reduzida)

            var screenLeft = Math.round(relLeft + imgW * leftRatio);
            var screenTop = Math.round(relTop + imgH * topRatio);
            var screenWidth = Math.round(imgW * widthRatio);
            var screenHeight = Math.round(imgH * heightRatio);

            calcScreen.style.position = 'absolute';
            calcScreen.style.left = screenLeft + 'px';
            calcScreen.style.top = screenTop + 'px';
            calcScreen.style.width = screenWidth + 'px';
            calcScreen.style.height = screenHeight + 'px';
            calcScreen.style.transform = '';
            calcScreen.style.zIndex = 5;
            calcScreen.style.background = 'rgba(0,0,0,0.85)';
            calcScreen.style.display = 'flex';
            calcScreen.style.alignItems = 'center';
            calcScreen.style.justifyContent = 'flex-end';
            calcScreen.style.padding = '6px 10px';
            calcScreen.style.boxSizing = 'border-box';
            calcScreen.style.fontFamily = '"Courier New", monospace';
            calcScreen.style.fontSize = Math.max(12, Math.round(screenHeight * 0.45)) + 'px';
        }

        if (calcImg) {
            // garantir alinhamento quando a imagem carregar e ao redimensionar
            calcImg.addEventListener('load', alignCalculatorScreen);
            window.addEventListener('resize', alignCalculatorScreen);
            // chamada inicial (pode ser necessária se a imagem já estiver carregada)
            setTimeout(alignCalculatorScreen, 50);
        }

        // Atualiza lista de inimigos e options dos selects
        function updateEnemyOptions() {
            var enemyRows = enemiesTableBody ? enemiesTableBody.querySelectorAll('tr') : [];
            var enemies = [];

            enemyRows.forEach(function (row, index) {
                var nameInput = row.querySelector('.enemy-name');
                var caInput = row.querySelector('.enemy-ca');
                var parInput = row.querySelector('.enemy-paralyzed');

                var name = nameInput ? nameInput.value.trim() : '';
                var ca = caInput ? parseInt(caInput.value, 10) : NaN;
                var paralyzed = parInput ? !!parInput.checked : false;

                if (name && !isNaN(ca)) {
                    var id = 'enemy-' + index;
                    enemies.push({
                        id: id,
                        name: name,
                        ca: ca,
                        paralyzed: paralyzed
                    });
                    row.dataset.enemyId = id;
                } else {
                    row.dataset.enemyId = '';
                }
            });

            var selects = widget.querySelectorAll('.group-enemy, .player-enemy');
            selects.forEach(function (select) {
                var currentValue = select.value;
                while (select.firstChild) {
                    select.removeChild(select.firstChild);
                }

                var optDefault = document.createElement('option');
                optDefault.value = '';
                optDefault.textContent = 'Selecione...';
                select.appendChild(optDefault);

                enemies.forEach(function (enemy) {
                    var opt = document.createElement('option');
                    opt.value = enemy.id;
                    opt.textContent = enemy.name + ' (CA ' + enemy.ca + ')';
                    select.appendChild(opt);
                });

                if (currentValue) {
                    select.value = currentValue;
                }
            });

            return enemies;
        }

        // Inicializa opções de inimigos na primeira carga
        updateEnemyOptions();

        // Adicionar inimigo
        if (addEnemyBtn && enemiesTableBody) {
            addEnemyBtn.addEventListener('click', function () {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="enemy-name" value="Inimigo"></td>' +
                    '<td><input type="number" class="enemy-ca" value="15" min="1" max="40"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" class="enemy-paralyzed"></td>' +
                    '<td><button type="button" class="remove-enemy">X</button></td>';
                enemiesTableBody.appendChild(tr);
                updateEnemyOptions();
            });
        }

        // Adicionar grupo
        if (addGroupBtn && groupsTableBody) {
            addGroupBtn.addEventListener('click', function () {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="group-name" value="Grupo"></td>' +
                    '<td><select class="group-enemy"><option value="">Selecione...</option></select></td>' +
                    '<td><input type="number" class="group-creatures" value="1" min="1"></td>' +
                    '<td><input type="number" class="group-attacks" value="1" min="1"></td>' +
                    '<td><input type="text" class="group-attack-roll" value="1d20+5"></td>' +
                    '<td><input type="text" class="group-damage-roll" value="1d6+3"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" class="group-advantage"></td>' +
                    '<td><button type="button" class="remove-group">X</button></td>';
                groupsTableBody.appendChild(tr);
                updateEnemyOptions();
            });
        }

        // Adicionar personagem
        if (addPlayerBtn && playersTableBody) {
            addPlayerBtn.addEventListener('click', function () {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="player-name" value="Personagem"></td>' +
                    '<td><select class="player-enemy"><option value="">Selecione...</option></select></td>' +
                    '<td><input type="number" class="player-attacks" value="1" min="1"></td>' +
                    '<td><input type="text" class="player-attack-roll" value="1d20+5"></td>' +
                    '<td><input type="text" class="player-damage-roll" value="1d6+3"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" class="player-advantage"></td>' +
                    '<td><button type="button" class="remove-player">X</button></td>';
                playersTableBody.appendChild(tr);
                updateEnemyOptions();
            });
        }

        // Remover inimigo / grupo / personagem (delegação de eventos)
        widget.addEventListener('click', function (e) {
            if (e.target.classList.contains('remove-enemy')) {
                var row = e.target.closest('tr');
                if (row && enemiesTableBody.rows.length > 1) {
                    row.parentNode.removeChild(row);
                    updateEnemyOptions();
                }
            }

            if (e.target.classList.contains('remove-group')) {
                var grow = e.target.closest('tr');
                if (grow && groupsTableBody.rows.length > 1) {
                    grow.parentNode.removeChild(grow);
                }
            }

            if (e.target.classList.contains('remove-player')) {
                var prow = e.target.closest('tr');
                if (prow && playersTableBody.rows.length > 1) {
                    prow.parentNode.removeChild(prow);
                }
            }
        });

        // Sempre que alterar inimigos, atualizar selects
        widget.addEventListener('input', function (e) {
            if (
                e.target.classList.contains('enemy-name') ||
                e.target.classList.contains('enemy-ca') ||
                e.target.classList.contains('enemy-paralyzed')
            ) {
                updateEnemyOptions();
            }
        });

        // Botão Rolar turno
        if (runRollsBtn) {
            runRollsBtn.addEventListener('click', function () {
                var enemies = updateEnemyOptions();
                var enemyMap = {};
                enemies.forEach(function (en) {
                    enemyMap[en.id] = en;
                });

                var allByEnemy = {}; // enemyId => { enemy, attacks: [], hits: 0, totalDamage: 0 }

                function ensureEnemyBlock(enemyId) {
                    if (!allByEnemy[enemyId]) {
                        allByEnemy[enemyId] = {
                            enemy: enemyMap[enemyId],
                            attacks: [],
                            hits: 0,
                            totalDamage: 0
                        };
                    }
                    return allByEnemy[enemyId];
                }

                // --------- GRUPOS ----------
                if (groupsTableBody) {
                    var groupRows = groupsTableBody.querySelectorAll('tr');
                    groupRows.forEach(function (row) {
                        var groupNameInput = row.querySelector('.group-name');
                        var enemySelect = row.querySelector('.group-enemy');
                        var creaturesInput = row.querySelector('.group-creatures');
                        var attacksInput = row.querySelector('.group-attacks');
                        var attackRollInput = row.querySelector('.group-attack-roll');
                        var damageRollInput = row.querySelector('.group-damage-roll');
                        var advantageCheckbox = row.querySelector('.group-advantage');

                        var enemyId = enemySelect ? enemySelect.value : '';
                        if (!enemyId || !enemyMap[enemyId]) return;

                        var enemy = enemyMap[enemyId];
                        var numCreatures = creaturesInput && creaturesInput.value ? parseInt(creaturesInput.value, 10) : 0;
                        var attacksPerCreature = attacksInput && attacksInput.value ? parseInt(attacksInput.value, 10) : 0;
                        var attackNotation = attackRollInput ? attackRollInput.value.trim() : '';
                        var damageNotation = damageRollInput ? damageRollInput.value.trim() : '';
                        var advantageType = advantageCheckbox && advantageCheckbox.checked ? 'adv' : 'none';
                        var groupName = groupNameInput && groupNameInput.value.trim() ? groupNameInput.value.trim() : 'Grupo';

                        if (numCreatures <= 0 || attacksPerCreature <= 0 || !attackNotation) return;

                        var enemyBlock = ensureEnemyBlock(enemyId);

                        for (var ci = 1; ci <= numCreatures; ci++) {
                            for (var ai = 1; ai <= attacksPerCreature; ai++) {
                                var atk = rollAttack(attackNotation, advantageType, enemy.ca, enemy.paralyzed);
                                var dmg = null;

                                if (!atk.error && atk.hit) {
                                    if (damageNotation) {
                                        dmg = rollDamage(damageNotation, atk.isCrit);
                                        if (!dmg.error) {
                                            enemyBlock.totalDamage += dmg.total;
                                            enemyBlock.hits += 1;
                                        }
                                    } else {
                                        enemyBlock.hits += 1;
                                    }
                                }

                                enemyBlock.attacks.push({
                                    label: groupName + ' - Criatura ' + ci + ', Ataque ' + ai + ':',
                                    attack: atk,
                                    damage: dmg
                                });
                            }
                        }
                    });
                }

                // --------- PERSONAGENS ----------
                if (playersTableBody) {
                    var playerRows = playersTableBody.querySelectorAll('tr');
                    playerRows.forEach(function (row) {
                        var nameInput = row.querySelector('.player-name');
                        var enemySelect = row.querySelector('.player-enemy');
                        var attacksInput = row.querySelector('.player-attacks');
                        var attackRollInput = row.querySelector('.player-attack-roll');
                        var damageRollInput = row.querySelector('.player-damage-roll');
                        var advantageCheckbox = row.querySelector('.player-advantage');

                        var enemyId = enemySelect ? enemySelect.value : '';
                        if (!enemyId || !enemyMap[enemyId]) return;

                        var enemy = enemyMap[enemyId];
                        var attacksPerTurn = attacksInput && attacksInput.value ? parseInt(attacksInput.value, 10) : 0;
                        var attackNotation = attackRollInput ? attackRollInput.value.trim() : '';
                        var damageNotation = damageRollInput ? damageRollInput.value.trim() : '';
                        var advantageType = advantageCheckbox && advantageCheckbox.checked ? 'adv' : 'none';
                        var playerName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : 'Personagem';

                        if (attacksPerTurn <= 0 || !attackNotation) return;

                        var enemyBlock = ensureEnemyBlock(enemyId);

                        for (var ai = 1; ai <= attacksPerTurn; ai++) {
                            var atk = rollAttack(attackNotation, advantageType, enemy.ca, enemy.paralyzed);
                            var dmg = null;

                            if (!atk.error && atk.hit) {
                                if (damageNotation) {
                                    dmg = rollDamage(damageNotation, atk.isCrit);
                                    if (!dmg.error) {
                                        enemyBlock.totalDamage += dmg.total;
                                        enemyBlock.hits += 1;
                                    }
                                } else {
                                    enemyBlock.hits += 1;
                                }
                            }

                            enemyBlock.attacks.push({
                                label: playerName + ', Ataque ' + ai + ':',
                                attack: atk,
                                damage: dmg
                            });
                        }
                    });
                }

                // ---------- Montar HTML dos resultados ----------
                var enemyIds = Object.keys(allByEnemy);
                var html = '';
                var globalDamage = 0;

                if (enemyIds.length === 0) {
                    html = '<p>Nenhum ataque foi rolado. Verifique se há inimigos, grupos ou personagens configurados.</p>';
                } else {
                    enemyIds.forEach(function (enemyId) {
                        var block = allByEnemy[enemyId];
                        var enemy = block.enemy;
                        var attacks = block.attacks;
                        var hits = block.hits;
                        var totalDamage = block.totalDamage;

                        globalDamage += totalDamage;

                        html += '<div class="enemy-block">';
                        html += '<h4>Inimigo: ' + escapeHtml(enemy.name) + ' (CA ' + enemy.ca + ')';
                        if (enemy.paralyzed) {
                            html += ' (paralisado: ataques contam como crítico)';
                        }
                        html += '</h4>';

                        html += '<div class="enemy-summary">';
                        html += 'Ataques recebidos: ' + attacks.length + ' | Acertos: ' + hits + ' | Dano total: ' + totalDamage;
                        html += '</div>';

                        attacks.forEach(function (entry) {
                            var atk = entry.attack;
                            var dmg = entry.damage;

                            if (atk.error) {
                                html += '<div class="attack-result attack-result-miss">';
                                html += '<strong>' + escapeHtml(entry.label) + '</strong> ';
                                html += '<span class="attack-meta">' + escapeHtml(atk.error) + '</span>';
                                html += '</div>';
                                return;
                            }

                            var isHit = atk.hit === true;
                            var resultClass = isHit ? 'attack-result-hit' : 'attack-result-miss';

                            html += '<div class="attack-result ' + resultClass + '">';
                            html += '<strong>' + escapeHtml(entry.label) + '</strong><br>';

                            // Ataque
                            html += 'Ataque: ';
                            if (atk.d2 !== null) {
                                html += '(' + atk.d1 + ' / ' + atk.d2 + ') → ';
                                html += '<strong>' + atk.chosen + '</strong>';
                            } else {
                                html += '(' + atk.d1 + ')';
                            }

                            if (atk.bonus !== 0) {
                                if (atk.bonus > 0) {
                                    html += ' + ' + atk.bonus;
                                } else {
                                    html += ' - ' + Math.abs(atk.bonus);
                                }
                            }

                            html += ' = <strong>' + atk.totalAttack + '</strong>';

                            if (typeof atk.enemyCA === 'number') {
                                html += ' → CA ' + atk.enemyCA + ' → ';
                                if (isHit) {
                                    if (atk.isCrit) {
                                        html += 'ACERTO CRÍTICO';
                                    } else {
                                        html += 'ACERTOU';
                                    }
                                } else {
                                    html += 'ERROU';
                                }
                            }

                            // Dano
                            if (dmg && !dmg.error) {
                                var rollStrings = dmg.rolls.map(function (r) {
                                    return (r >= 0 ? r : ('-' + Math.abs(r)));
                                });

                                html += '<br>Dano: ' + escapeHtml(dmg.notation) + ' → [' + rollStrings.join(', ') + ']';

                                if (dmg.flat !== 0) {
                                    if (dmg.flat > 0) {
                                        html += ' + ' + dmg.flat;
                                    } else {
                                        html += ' - ' + Math.abs(dmg.flat);
                                    }
                                }

                                html += ' = <strong>' + dmg.total + '</strong>';
                                if (dmg.crit) {
                                    html += ' (crítico, dados dobrados)';
                                }
                            } else if (dmg && dmg.error) {
                                html += '<br><span class="attack-meta">' + escapeHtml(dmg.error) + '</span>';
                            }

                            html += '</div>';
                        });

                        html += '</div>';
                    });

                    html += '<p class="total-damage-summary"><strong>Dano total causado: ' + globalDamage + '</strong></p>';
                }

                resultsContainer.innerHTML = html;
                // Atualizar visor da calculadora com o dano total
var calcScreen = widget.querySelector('.rolagens-dnd-calculator-screen');
if (calcScreen) {
    calcScreen.textContent = globalDamage || 0;
}

            });
        }
    }

    // Inicializar todos os widgets da página
    function initAllWidgets() {
        var widgets = document.querySelectorAll('.rolagens-dnd-widget');
        widgets.forEach(function (widget) {
            initWidget(widget);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllWidgets);
    } else {
        initAllWidgets();
    }
})();
