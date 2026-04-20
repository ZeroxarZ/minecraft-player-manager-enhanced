<?php

return [

    'title' => 'Jugadores del servidor',

    'columns' => [
        'avatar' => 'Avatar',
        'name' => 'Nombre de usuario',
        'status' => 'Estado',
        'world' => 'Mundo',
        'online' => 'Conectado',
        'offline' => 'Desconectado',
        'op' => 'Operador',
    ],

    'filters' => [
        'all' => 'Todos',
        'online' => 'Conectados',
        'offline' => 'Desconectados',
        'op' => 'OP',
        'banned' => 'Prohibidos',
    ],

    'sections' => [
        'identity' => 'Identidad',
        'statistics' => 'Estadísticas',
        'statistics_desc' => 'Datos históricos de las estadísticas del mundo',
        'live_status' => 'Estado en vivo',
        'live_status_desc' => 'Datos en tiempo real desde el servidor',
        'offline_status_desc' => 'Desconectado - Mostrando datos del último archivo guardado',
        'rcon_disabled_status_desc' => 'RCON desactivado - Mostrando datos del archivo guardado',
        'inventory' => 'Inventario',
        'management' => 'Gestión',
        'management_desc' => 'Realizar acciones sobre este jugador',
    ],

    'fields' => [
        'username' => 'Nombre de usuario',
        'current_status' => 'Estado actual',
        'uuid' => 'UUID',
        'play_time' => 'Tiempo de juego',
        'distance_walked' => 'Distancia recorrida',
        'mobs_killed' => 'Mobs eliminados',
        'deaths' => 'Muertes',
        'status' => 'Estado',
        'xp_level' => 'Nivel de XP',
        'gamemode' => 'Modo de juego',
        'visual_inventory' => 'Inventario visual',
    ],

    'stats' => [
        'health' => 'Salud',
        'food' => 'Comida',
    ],

    'actions' => [
        'view' => 'Ver',

        'op' => [
            'label_op' => 'OP',
            'label_deop' => 'Quitar OP',
            'heading_op' => 'Conceder estado de operador',
            'heading_deop' => 'Revocar estado de operador',
            'desc_op' => '¿Seguro que quieres convertir a este jugador en operador (OP)?',
            'desc_deop' => '¿Seguro que quieres quitar los privilegios de OP a este jugador?',
            'notify_op' => 'Comando OP enviado',
            'notify_deop' => 'Comando DEOP enviado',
        ],

        'clear_inventory' => [
            'label' => 'Vaciar inv.',
            'desc' => '¿Seguro que quieres vaciar el inventario de este jugador? Esta acción no se puede deshacer.',
            'notify' => 'Comando para vaciar inventario enviado',
        ],

        'kick' => [
            'label' => 'Expulsar',
            'reason' => 'Motivo',
            'default_reason' => 'Expulsado por un operador',
            'notify' => 'Comando de expulsión enviado',
        ],

        'ban' => [
            'label_ban' => 'Bloquear',
            'label_unban' => 'Desbloquear',
            'reason' => 'Motivo',
            'default_reason' => 'Bloqueado por un operador',
            'notify_ban' => 'Comando de bloqueo enviado',
            'notify_unban' => 'Comando de desbloqueo enviado',
        ],
    ],

    'widget' => [
        'online_players' => 'Jugadores conectados',
        'motd' => 'Mensaje del día',
        'map' => 'Nombre del mapa',
        'units' => [
            'mins' => 'mins',
        ],
    ],

    'pages' => [
        'list' => 'Lista de jugadores',
        'view' => 'Ver jugador',
    ],

    'values' => [
        'survival' => 'Supervivencia',
        'creative' => 'Creativo',
        'adventure' => 'Aventura',
        'spectator' => 'Espectador',
        'online' => 'Conectado',
        'offline' => 'Desconectado',
        'offline_data_source' => 'Desconectado (datos del último guardado)',
    ],

    'units' => [
        'mins' => 'mins',
    ],

    'settings' => [
        'rcon_enabled' => 'Habilitar RCON / Estado en vivo',
        'rcon_enabled_helper' => 'Habilita la obtención de datos en tiempo real (inventario, salud, etc.) mediante RCON. Requiere RCON activado en server.properties.',
        'rcon_host' => 'Host / IP de RCON',
        'rcon_host_helper' => 'Dirección IP o nombre de host para la conexión RCON (ejemplo: 127.0.0.1).',
        'rcon_port' => 'Puerto RCON',
        'rcon_port_helper' => 'Puerto TCP de RCON (predeterminado: 25575).',
        'rcon_password' => 'Contraseña RCON',
        'rcon_password_helper' => 'Contraseña RCON configurada en server.properties o en los ajustes de inicio del servidor.',
        'nav_sort' => 'Orden de navegación',
        'nav_sort_helper' => 'Orden en el menú lateral. Los números más bajos aparecen más arriba. (Predeterminado: 2)',
        'saved' => 'Configuración guardada correctamente.',
    ],

];
