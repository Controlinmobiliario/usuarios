# usuarios

 docker swarm init<br>
 docker network create --driver=overlay --attachable sharednet<br>
 docker stack deploy -c docker-compose.yml usuarios<br>
