# usuarios

 docker swarm init
 docker network create --driver=overlay --attachable sharednet
 docker stack deploy -c docker-compose.yml usuarios
