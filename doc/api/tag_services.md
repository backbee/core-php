# Documentation des services de l'API des tags

Cette documentation a pour but de lister tous les services offerts par l'API tags de BackBee.com.

## Récupérer les tags

**`GET /api/tags`**

**Description** : permet de récupérer une collection de tags.

**Paramètres** :

Les paramètres doivent être passé en *query string*.

Nom | Obligatoire | Commentaire
-|:-:|-
**term** | Non | Permet de filtrer par libellé du tag
**range** | Non | Permet de récupérer une collection de tag à partir d'un index donné et de contrôler le nombre de tag maximum récupéré par requête. *Exemple :* `range=10-19` signifie qu'on veut récupérer 10 éléments (`=19 - 10 + 1`; on ajoute `+1` car l'index commence à 0) à partir de l'index 10. Le nombre de tags récupéré au maximum en une fois est de 50. Si ce paramètre `range` est omis, la collection retournée commencera à l'index 0 et contiendra 15 tags au maximum.

*Exemple* :

```
curl -X GET http://standalone.local/api/tags
< 200 OK
< Content-Type: application/json
< [
  {
    "uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "keyword": "city",
    "has_children": true,
    "parent_uid": null,
    "parents": [],
    "created": 1560864394,
    "modified": 1560864394,
    "translations": {
      "fr": "ville"
    }
  },
  {
    "uid": "06bf7bd74a342861e025fd3d37b24271",
    "keyword": "nice",
    "has_children": false,
    "parent_uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "parents": [
      "city"
    ],
    "created": 1560864265,
    "modified": 1560864265,
    "translations": []
  },
  {
    "uid": "6f31fc6dd01d3a69623c0ad473357ee2",
    "keyword": "paris",
    "has_children": false,
    "parent_uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "parents": [
      "city"
    ],
    "created": 1560864351,
    "modified": 1560864351,
    "translations": []
  }
]
```

**Réponse :**

Le format de la réponse est en JSON. Selon les cas, vous pouvez avoir deux codes de statut de réponse différents :

- `200 OK` lorsque le contenu de la réponse contient tous les tags
- `206 Partial Content` lorsque de la réponse renvoie qu'une partie des tags

## Récupérer un tag par son identifiant

**`GET /api/tags/{uid}`**

**Description** : permet de récupérer un tag à l'aide son identifiant.

*Exemple* :

```
curl -X GET http://standalone.local/api/tags/56c0dfa9b339f35bca95f1318b5ff920
< 200 OK
< Content-Type: application/json
< {
  "uid": "56c0dfa9b339f35bca95f1318b5ff920",
  "keyword": "city",
  "has_children": true,
  "parent_uid": null,
  "parents": [],
  "created": 1560864394,
  "modified": 1560864394,
  "translations": {
    "fr": "ville"
  }
}
```

**Réponse :**

Le format de la réponse est en JSON. Le code de statut sera toujours `200 OK` si le tag existe. Vous aurez une réponse avec un statut `404 Not Found` si l'identifiant du tag fourni n'est pas reconnu.

## Récupérer les tags de premier niveau (arborescence)

**`GET /api/tags/tree-first-level`**

**Description** : permet de récupérer une collection de tags de premier niveau (qui n'ont pas de tag parent).

**Paramètres** :

Les paramètres doivent être passé en *query string*.

Nom | Obligatoire | Commentaire
-|:-:|-
**range** | Non | Permet de récupérer une collection de tag à partir d'un index donné et de contrôler le nombre de tag maximum récupéré par requête. *Exemple :* `range=10-19` signifie qu'on veut récupérer 10 éléments (`=19 - 10 + 1`; on ajoute `+1` car l'index commence à 0) à partir de l'index 10. Le nombre de tags récupéré au maximum en une fois est de 50. Si ce paramètre `range` est omis, la collection retournée commencera à l'index 0 et contiendra 15 tags au maximum.

*Exemple* :

```
curl -X GET http://standalone.local/api/tags/tree-first-level
< 200 OK
< Content-Type: application/json
< [
  {
    "uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "keyword": "city",
    "has_children": true,
    "parent_uid": null,
    "parents": [],
    "created": 1560864394,
    "modified": 1560864394,
    "translations": {
      "fr": "ville"
    }
  }
]
```

**Réponse :**

Le format de la réponse est en JSON. Selon les cas, vous pouvez avoir deux codes statut de réponse différents :

- `200 OK` lorsque le contenu de la réponse contient tous les tags
- `206 Partial Content` lorsque de la réponse renvoie qu'une partie des tags

## Récupérer tous les enfants d'un tag

**`GET /api/tags/{uid}/children`**

**Description** : permet de récupérer tous les enfants du tag correspond à l'`uid` fourni.

*Exemple* :

```
curl -X GET http://standalone.local/api/tags/56c0dfa9b339f35bca95f1318b5ff920/children
< 200 OK
< Content-Type: application/json
< [
  {
    "uid": "06bf7bd74a342861e025fd3d37b24271",
    "keyword": "nice",
    "has_children": false,
    "parent_uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "parents": [
      "city"
    ],
    "created": 1560864265,
    "modified": 1560864265,
    "translations": []
  },
  {
    "uid": "6f31fc6dd01d3a69623c0ad473357ee2",
    "keyword": "paris",
    "has_children": false,
    "parent_uid": "56c0dfa9b339f35bca95f1318b5ff920",
    "parents": [
      "city"
    ],
    "created": 1560864351,
    "modified": 1560864351,
    "translations": []
  }
]
```

**Réponse :**

Le format de la réponse est en JSON. Le code de statut sera toujours `200 OK` si le tag parent existe. Vous aurez une réponse avec un statut `404 Not Found` si l'identifiant du tag parent fourni n'est pas reconnu.
Si la réponse est un tableau vide, cela signifie que le tag parent n'a pas d'enfant.

## Récupérer toutes les pages qui utilise un tag donné

**`GET /api/tags/{uid}/linked-pages`**

**Description** : permet de récupérer toutes les pages qui utilisent le tag correspondant à l'`uid` fourni.

*Exemple* :

```
curl -X GET http://standalone.local/api/tags/56c0dfa9b339f35bca95f1318b5ff920/linked-pages
< 200 OK
< Content-Type: application/json
< [
  {
    "id": "70010c658061df318378ceb1fee05448",
    "title": "France Cities"
  }
]
```

**Réponse :**

Le format de la réponse est en JSON. Le code de statut sera toujours `200 OK` si le tag existe. Vous aurez une réponse avec un statut `404 Not Found` si l'identifiant du tag fourni n'est pas reconnu.
Si la réponse est un tableau vide, cela signifie que le tag n'est associé à aucune page.

## Créer un tag

**`POST /api/tags`**

**Description** : permet de créer un nouveau tag.

**Paramètres** :

Les paramètres doivent être dans le corps de la requête au format JSON et il faut ajouter le header `Content-Type: application/json`.

Nom | Obligatoire | Commentaire
-|:-:|-
**name** | Oui | le nom du tag
**parent_uid** | Non | l'`uid` du tag parent
**translations** | Non | Doit être un tableau, avec en clé l'identifiant de la langue et en valeur la traduction du tag associé à la clé.

*Exemple* :
```
curl -X POST \
-H "Content-Type: application/json" \
-d '{
    "name": "Hello",
    "parent_uid": null,
    "translations": {
        "fr": "Bonjour",
        "es": "Hola"
    }
}' \
http://standalone.local/api/tags
< 201 Created
< {
  "uid": "aa8ebe6fae09787e67a0353f7f2a4598",
  "keyword": "Hello",
  "has_children": false,
  "parent_uid": null,
  "parents": [],
  "created": 1561030975,
  "modified": 1561030975,
  "translations": {
    "fr": "Bonjour",
    "es": "Hola"
  }
}
```

**Les réponses possibles :**

- Si tout se passe correctement, vous aurez une réponse JSON avec le code statut `201 Created`. Vous aurez aussi la représentation JSON de la ressource nouvellement créée.

- Si vous tentez de créer un tag avec un `parent_uid` non reconnu, vous aurez une réponse d'erreur avec le statut `400 Bad Request` :
```
{
  "error": "bad_request",
  "reason": "Cannot find parent tag with provided uid (:123456)."
}
```

- Si vous tentez de recréer un tag déjà existant, vous aurez une réponse d'erreur avec le statut `400 Bad Request` :
```
{
  "error": "bad_request",
  "reason": "Cannot create tag (:Hello) because it already exists."
}
```

## Mettre à jour un tag

**`PUT /api/tags/{uid}`**

**Description** : permet de mettre à jour un tag.

**Paramètres** :

Les paramètres doivent être dans le corps de la requête au format JSON et il faut ajouter le header `Content-Type: application/json`.

Nom | Obligatoire | Commentaire
-|:-:|-
**name**  | Oui | Le nouveau libellé du tag
**parent_uid** | Non | Le nouveau tag parent; à noter que vous pouvez supprimer une liaison parent/enfant en envoyant `null`
**translations** | Non | Doit être un tableau, avec en clé l'identifiant de la langue et en valeur la traduction du tag associé à la clé. A noter que vous pouvez supprimer une traduction en envoyant `null` ou une chaîne vide pour la langue correspondante.

*Exemple* :
```
curl -X PUT \
-H "Content-Type: application/json" \
-d '{
    "name":"town",
    "parent_uid": null,
    "translations": {
        "es":"cuidad",
        "fr":"ville"
    }
}' \
http://standalone.local/api/tags/56c0dfa9b339f35bca95f1318b5ff920
< 204 No Content
```
**Les réponses possibles :**

- Si tout se passe correctement, vous aurez une réponse avec le code statut `204 No Content`.

- Si vous tentez de mettre à jour un tag qui n'existe pas, vous aurez une réponse d'erreur avec le statut `404 Not Found` :
```
{
  "error": "not_found",
  "reason": "Cannot find tag with provided uid (:56c0dfa9b339f35bca95f1318b3ff920)"
}
```

- Si vous voulez mettre à jour un tag avec un `parent_uid` non reconnu, vous aurez une réponse d'erreur avec le statut `400 Bad Request` :
```
{
  "error": "bad_request",
  "reason": "Cannot find parent tag with provided uid (:123456)."
}
```

- Si vous tentez de renommer un tag avec un libellé déjà existant, vous aurez une réponse d'erreur avec le statut `400 Bad Request` :

```
{
  "error": "bad_request",
  "reason": "cannot rename tag to \"hello\" because tag with this name already exists."
}
```

## Supprimer un tag

**`DELETE /api/tags/{uid}`**

**Description** : permet de supprimer un tag grâce à son `uid`.

*Exemple* :

```
curl -X DELETE http://api.backbeeplanet.com/api/tags/aa8ebe6fae09787e67a0353f7f2a4598
< 204 No Content
```

**Les réponses possibles :**

- Si tout se passe correctement, vous aurez une réponse avec le code statut `204 No Content`.

- Si vous tentez de supprimer un tag qui n'existe pas, vous aurez une réponse d'erreur avec le statut `404 Not Found` :
```
{
  "error": "not_found",
  "reason": "Cannot find tag with provided uid (:56c0dfa9b339f35bca95f1318b3ff920)"
}
```

- Si vous tentez de supprimer un tag qui possède des enfants, vous aurez une réponse d'erreur avec le statut `400 Bad Request`. Pour supprimer un tag qui a des enfants, il faut d'abord supprimer tous les tags enfants ou briser tous les liens parent-enfant :

```
{
  "error": "bad_request",
  "reason": "Cannot delete tag because it has children."
}
```
