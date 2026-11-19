# Public press-release profile protocol

Namespace `Hexa\PluginCore\PressReleaseProfiles`, folder `src/PressReleaseProfiles/`, class `PressReleaseProfilesClient`. Hosts own source-origin settings, selected identities, UI, endpoint serving policy and escaped output. The client owns only the connection and response checks. It does not store credentials or call an AI provider.

Instantiate the client with no arguments. Every method takes the HTTPS site origin first:

- `author($origin, $id)`: `GET /wp-json/hprwc/v1/authors/{id}`.
- `find_author($origin, $profile_url)`: `GET /wp-json/hprwc/v1/authors?profile_url=...`, exact canonical author URL on the source.
- `author_releases($origin, $author_id, $page=1)`: `GET /wp-json/hprwc/v1/authors/{id}/releases`.
- `search_people($origin, $query, $page=1)`: `GET /wp-json/hprwc/v1/verified-profiles?q=...`.
- `person($origin, $id)`: `GET /wp-json/hprwc/v1/verified-profiles/{id}`.
- `releases($origin, $profile_id, $page=1)`: `GET /wp-json/hprwc/v1/verified-profiles/{id}/releases`.

Success is `{success:true,data:...}`. Failure is `{success:false,code:string,message:string}`. The client refuses non-HTTPS or non-origin inputs, private-network HTTP targets, redirects, oversized responses and malformed envelopes/identity/list shapes. It returns connection failures without response-body or transport-detail leakage.

Author data: `{id:int,name:string,url:string,image_url:string}`. Person adds `entity_type:"person"`. Image URLs may be empty. Identity URLs are canonical on the configured origin. Author and person identities are distinct; consumers must not substitute one ID for the other.

Search data: `{items:Person[],page:int,total:int,total_pages:int}`. Search terms are 2-120 characters. Empty/invalid terms return an empty list. Only published, unprotected person profiles are served. Results use 20 items per page.

Release-list data: `{author:Author|profile:Person,items:Release[],page:int,total:int,total_pages:int}`. Release: `{id:int,title:string,url:string,date_gmt:string,excerpt:string}`. `date_gmt` is ISO 8601 UTC. Only published, unprotected posts in the serving host's configured release category are included; person matching follows the stored profile association, author matching follows the post author. Empty lists succeed. Missing/nonpublic identities return HTTP 404 with a failure envelope. Pagination is 1-1000, with 20 items per page.

Example usage: `(new PressReleaseProfilesClient())->find_author($origin, $profile_url)`, then save only the confirmed identity. Use `author_releases` for authored releases and `releases` for releases about a selected person.

Verification method: consume the serving host's public endpoints with this client, checking separate author/person identities and lists. No test or provider request is run by installing the client.
