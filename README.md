# Mock-Server

HTTP mocking application for testing

## Installing
run docker image

```shell
~$ docker pull lav45/mock-server:latest
~$ docker run --rm -i -v $(pwd)/mocks:/mocks -p 8080:8080 lav45/mock-server:latest
```

## Examples
Create json file in the `./mocks` folder

### Hello world!
```shell
~$ cat ./mocks/test.json
```
```json
[{
    "response": {
        "body": "Hello world!"
    }
}]
```
Open: http://0.0.0.0:8080/

### Resource method
```json
[
    {
        "request": {
            "method": "GET",
            "path": "/user",
            "headers": {
                "content-type": "application/json"
            }
        },
        "response": {
            "status": 200,
            "headers": {
                "content-type": "application/json"
            },
            "body": {
                "method": "GET /user",
                "status": "OK"
            }
        }
    },
    {
        "request": {
            "method": "POST",
            "path": "/user",
            "headers": {
                "content-type": "application/json"
            }
        },
        "response": {
            "status": 200,
            "headers": {
                "content-type": "application/json"
            },
            "body": {
                "method": "POST /user",
                "status": "OK"
            }
        }
    }
]
```
Open: GET http://0.0.0.0:8080/user
Open: POST http://0.0.0.0:8080/user

### With routing params
`request.path`: "/user/{id}" rewrite in `response.body.method`: "GET /user/{id}"
```json
[
    {
        "request": {
            "method": "GET",
            "path": "/user/{id}",
            "headers": {
                "content-type": "application/json"
            }
        },
        "response": {
            "status": 200,
            "headers": {
                "content-type": "application/json"
            },
            "body": {
                "id": "{id}",
                "method": "GET /user/{id}",
                "status": "OK"
            }
        }
    }
]
```
Open: GET http://0.0.0.0:8080/user/5