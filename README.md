# usuarios

 docker swarm init<br>
 docker network create --driver=overlay --attachable sharednet<br>
 docker compose up<br>
 docker compose down<br>
 docker stack deploy -c docker-compose.yml usuarios<br>
 docker stack rm usuarios


 <br><br>
 0 * * * * /usr/bin/php /ruta/proyecto/cleanup_blacklist.php

