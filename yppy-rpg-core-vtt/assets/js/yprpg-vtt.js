// assets/js/yprpg-vtt.js

(function() {
    if (typeof YPRPG_VTT_DATA === 'undefined') {
        console.error('YPRPG_VTT_DATA não definido.');
        return;
    }

    const ajaxUrl = YPRPG_VTT_DATA.ajax_url;
    const nonce   = YPRPG_VTT_DATA.nonce;
    const mesaId  = YPRPG_VTT_DATA.mesa_id;
    const gridCfg = YPRPG_VTT_DATA.grid || { largura: 20, altura: 20, altmax: 3 };

    let scene, camera, renderer, gridHelper;
    let raycaster, mouse;
    let tokens = [];
    let selectedToken = null;
    let altLevel = 0;

    function init() {
        const container = document.getElementById('yprpg-vtt-canvas');
        if (!container) {
            console.error('Container yprpg-vtt-canvas não encontrado.');
            return;
        }

        const width  = container.clientWidth;
        const height = container.clientHeight;

        // Cena
        scene = new THREE.Scene();
        scene.background = new THREE.Color(0x111111);

        // Câmera
        camera = new THREE.PerspectiveCamera(60, width / height, 0.1, 1000);
        camera.position.set(10, 10, 10);
        camera.lookAt(0, 0, 0);

        // Renderizador
        renderer = new THREE.WebGLRenderer({ antialias: true });
        renderer.setSize(width, height);
        container.innerHTML = '';
        container.appendChild(renderer.domElement);

        // Luz simples
        const light = new THREE.DirectionalLight(0xffffff, 1);
        light.position.set(10, 20, 10);
        scene.add(light);
        scene.add(new THREE.AmbientLight(0xffffff, 0.3));

        // Grid
        const size = Math.max(gridCfg.largura, gridCfg.altura);
        gridHelper = new THREE.GridHelper(size, size, 0x444444, 0x444444);
        scene.add(gridHelper);

        // Raycaster
        raycaster = new THREE.Raycaster();
        mouse = new THREE.Vector2();

        // Eventos
        renderer.domElement.addEventListener('click', onClick, false);
        window.addEventListener('resize', onResize, false);

        const altUp   = document.getElementById('yprpg-alt-up');
        const altDown = document.getElementById('yprpg-alt-down');
        if (altUp) {
            altUp.addEventListener('click', function() {
                altLevel++;
                if (altLevel > gridCfg.altmax) altLevel = gridCfg.altmax;
                updateLog('Altitude atual: ' + altLevel);
            });
        }
        if (altDown) {
            altDown.addEventListener('click', function() {
                altLevel--;
                if (altLevel < 0) altLevel = 0;
                updateLog('Altitude atual: ' + altLevel);
            });
        }

        // Buscar estado da mesa
        loadState();

        animate();
    }

    function onResize() {
        const container = document.getElementById('yprpg-vtt-canvas');
        if (!container || !renderer || !camera) return;
        const width  = container.clientWidth;
        const height = container.clientHeight;
        renderer.setSize(width, height);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    }

    function animate() {
        requestAnimationFrame(animate);
        if (camera) {
            // Gira devagar em torno da cena (orbital simples)
            camera.position.applyAxisAngle(new THREE.Vector3(0,1,0), 0.001);
            camera.lookAt(0, 0, 0);
        }
        renderer.render(scene, camera);
    }

    function onClick(event) {
        if (!renderer || !scene || !camera) return;

        const rect = renderer.domElement.getBoundingClientRect();
        mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        raycaster.setFromCamera(mouse, camera);

        // 1) Verifica se clicou em algum token
        const tokenMeshes = tokens.map(t => t.mesh);
        const intersectsTokens = raycaster.intersectObjects(tokenMeshes, false);

        if (intersectsTokens.length > 0) {
            const hit = intersectsTokens[0].object;
            selectTokenByMesh(hit);
            return;
        }

        // 2) Se não, tenta clicar no grid
        const intersectsGrid = raycaster.intersectObject(gridHelper, false);
        if (intersectsGrid.length > 0) {
            const point = intersectsGrid[0].point;
            if (selectedToken) {
                moveSelectedTokenTo(point.x, point.z, altLevel);
            }
        }
    }

    function selectTokenByMesh(mesh) {
        tokens.forEach(t => {
            t.mesh.material.emissive = new THREE.Color(0x000000);
        });

        const token = tokens.find(t => t.mesh === mesh);
        if (token) {
            selectedToken = token;
            token.mesh.material.emissive = new THREE.Color(0x333333);
            updateLog('Token selecionado: ' + token.id);
        }
    }

    function moveSelectedTokenTo(x, z, altitude) {
        if (!selectedToken) return;
        // Arredonda pro grid
        selectedToken.x = Math.round(x);
        selectedToken.z = Math.round(z);
        selectedToken.y = altitude; // nível de voo

        selectedToken.mesh.position.set(selectedToken.x, selectedToken.y, selectedToken.z);
        updateLog('Token ' + selectedToken.id + ' movido para (' + selectedToken.x + ', ' + selectedToken.y + ', ' + selectedToken.z + ').');

        saveState();
    }

    function updateLog(message) {
        const logDiv = document.getElementById('yprpg-vtt-log');
        if (!logDiv) return;
        const p = document.createElement('div');
        const now = new Date();
        const time = now.toLocaleTimeString();
        p.textContent = '[' + time + '] ' + message;
        logDiv.prepend(p);
    }

    // ----- Estado (Ajax) -----

    function loadState() {
        const formData = new FormData();
        formData.append('action', 'yprpg_get_mesa_state');
        formData.append('nonce', nonce);
        formData.append('mesa_id', mesaId);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) {
                console.error('Erro ao carregar estado da mesa:', data);
                updateLog('Erro ao carregar estado da mesa.');
                createDefaultTokens();
                return;
            }
            const state = data.data;
            setupTokensFromState(state);
            updateLog('Estado da mesa carregado.');
        })
        .catch(err => {
            console.error('Erro Ajax:', err);
            updateLog('Erro Ajax ao carregar estado. Usando estado padrão.');
            createDefaultTokens();
        });
    }

    function saveState() {
        const state = {
            tokens: tokens.map(t => ({
                id: t.id,
                x: t.x,
                y: t.y,
                z: t.z
            }))
        };

        const formData = new FormData();
        formData.append('action', 'yprpg_save_mesa_state');
        formData.append('nonce', nonce);
        formData.append('mesa_id', mesaId);
        formData.append('state', JSON.stringify(state));

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) {
                console.error('Erro ao salvar estado:', data);
                updateLog('Erro ao salvar estado.');
                return;
            }
            updateLog('Estado salvo.');
        })
        .catch(err => {
            console.error('Erro Ajax ao salvar estado:', err);
            updateLog('Erro Ajax ao salvar estado.');
        });
    }

    function setupTokensFromState(state) {
        // Remove tokens antigos
        tokens.forEach(t => {
            scene.remove(t.mesh);
        });
        tokens = [];

        if (!state || !Array.isArray(state.tokens)) {
            createDefaultTokens();
            return;
        }

        state.tokens.forEach((t, idx) => {
            const token = createTokenMesh(t.id || ('t'+(idx+1)), t.x || 0, t.y || 0, t.z || 0);
            tokens.push(token);
        });
    }

    function createDefaultTokens() {
        tokens.forEach(t => scene.remove(t.mesh));
        tokens = [];

        const defaults = [
            { id: 't1', x: 0,  y: 0, z: 0 },
            { id: 't2', x: 2,  y: 0, z: 2 },
            { id: 't3', x: -2, y: 0, z: -2 }
        ];

        defaults.forEach(d => {
            const token = createTokenMesh(d.id, d.x, d.y, d.z);
            tokens.push(token);
        });
    }

    function createTokenMesh(id, x, y, z) {
        const geometry = new THREE.SphereGeometry(0.3, 16, 16);
        const material = new THREE.MeshStandardMaterial({ color: 0xffffff });
        const mesh = new THREE.Mesh(geometry, material);
        mesh.position.set(x, y, z);
        scene.add(mesh);

        return {
            id: id,
            x: x,
            y: y,
            z: z,
            mesh: mesh
        };
    }

    // Inicializa quando a página carregar
    document.addEventListener('DOMContentLoaded', init);
})();