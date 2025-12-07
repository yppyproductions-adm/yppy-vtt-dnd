# yppy-vtt-dnd
Plugins de rolagem de dados e VTT para d20
🌳 RESUMO DO PROJETO YPPY VTT dnd + ROLAGENS DE DADOS

Você está criando um ecossistema completo de RPG dentro do WordPress, totalmente independente de serviços externos, rodando apenas com PHP + MySQL + JavaScript (Three.js).

O sistema será composto por dois plugins integrados, formando o núcleo do seu produto:

🎲 PLUGIN 1 — Rolagens Personalizadas de DnD (já iniciado)

Este plugin é uma ferramenta para:

Rolagens de ataque, dano, crítico

Cálculos automáticos de bônus e penalidades

Rolagens em massa (grupos de inimigos)

Rolagens condicionais (vantagem, desvantagem, paralisia etc.)

Configuração personalizada de fórmulas

Atalhos de rolagem com nome (ex.: “Golpe Duplo”, “Chama Ardente”)

Ele será integrado ao VTT para permitir:

atalhos de dano dentro do tabuleiro

cálculos automáticos de acertos e falhas

economia gigantesca de tempo para mestres e jogadores

Este plugin servirá como porta de entrada orgânica para atrair tráfego, já que muitos procuram “rolador de dados D&D”.

🧩 PLUGIN 2 — YPPY VTT (Mini MMO de RPG de Mesa)

Um VTT 3D minimalista, leve e inovador, oferecendo:

🟣 Recursos centrais:

Grid 3D real, permitindo altura e voo

Tokens minimalistas (bolinhas coloridas)

Movimentação suave no grid

Câmera livre (orbital + POV)

Cenários modulares para mestres

Mudança de cena por portas conectadas

Posicionamento e movimentação dos jogadores pelo celular

Painel do mestre com controle total

Registro automático da sessão (log interno)

🟢 Conteúdo vendável:

Cenários 3D prontos (dungeons, cidades, floresta etc.)

Packs de assets (árvores, rochas, paredes, iluminação etc.)

NPCs prontos com ficha, aparência e história

Assinaturas com funções avançadas (jogadores e mestres)

🟡 Integrações com o plugin de rolagem:

Atalhos configuráveis por personagem

Cálculo automático de acertos

Dano, crítico, resistência, salvaguarda

Ataque direcionado a tokens selecionáveis

🔵 Objetivo:

Criar o primeiro VTT 3D leve e universal, totalmente funcional dentro de um site WordPress, sem depender de servidores externos ou ferramentas pagas como Firebase.

🗺️ MAPA DO PROJETO — Versão MVP (Fase 1)

O MVP é a menor versão plenamente jogável.

🌱 PLUGINS QUE FORMAM O MVP
1. Plugin de Rolagem de Dados Personalizados (você já começou)

Shortcode funcional

Interface simples (início)

Rolagens de ataque e dano

Críticos e modificadores

Rolagem em massa

Salvaguardas básicas

➡️ Já é útil por si só e atrai tráfego para o site.

2. Plugin YPPY VTT — MVP
2.1. Motor 3D

Three.js r160 local

Grid 3D transparente

Células clicáveis

Altura configurável

2.2. Tokens

Tokens simples (bolinhas)

Cores personalizáveis

Um token por jogador

Tokens de inimigos controlados pelo mestre

2.3. Câmera

Controle orbital

Zoom

Colisão simples

Botão para câmera em POV

2.4. Sincronização multiplayer

Apenas com WordPress + Ajax

Atualização periódica dos estados (posições, turno etc.)

Sem Firebase, sem socket externo

2.5. Sessões

Mestre cria a sessão

Mestre compartilha link com jogadores

Jogadores entram e controlam seus tokens

2.6. Turnos e iniciativa

Lista de iniciativa simples

Avanço de turno

Indicação de quem está jogando

2.7. Integração com o plugin de rolagem

Botões de rolagem ao lado do token

Mestre pode forçar rolagens para inimigos

Resultado aparece no chat interno da sessão

2.8. Cenário

Cenário básico vazio

Mestre pode adicionar:

paredes simples

chão elevado

luzes

Salvamento no banco de dados
