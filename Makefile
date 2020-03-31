docker_build = (docker-compose build)

build:
	$(call docker_build)

test:
	$(call docker_build) && docker-compose run app composer run-script test
