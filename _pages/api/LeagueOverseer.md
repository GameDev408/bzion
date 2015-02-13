---
layout: page
title: League Overseer API
permalink: /api/LeagueOverseer/
include_menu: false
---

LeagueOverseer is a third-party plug-in written and maintained by one of the BZiON developers which makes use of BZiON's Post API. While the name of the API is a misnomer, it can be used by other services as well.

In order to access the LeagueOverseer API, you may make POST requests to the following URL with the respective parameters

    /api/leagueOverseer

## Team Information Dump

To fetch a complete list of players and their team affiliations, make a `teamInfoDump` query.

- Query: teamInfoDump
- Since: API v2

### Query

    query=teamInfoDump&apiVersion=2

### Response

```json
{
    "team_list": [
        {
          "team": "Good Separation",
          "members": "9736",
          "team_id": 7,
          "team_name": "Good Separation",
          "team_members": "9736"
        },
        {
          "team": "Fractious disinclination",
          "members": "34353,49434",
          "team_id": 8,
          "team_name": "Fractious disinclination",
          "team_members": "34353,49434"
        }
    ]
}
```

### Fields

| JSON Name    | API Version | Notes                                              |
| ------------ | ----------- | -------------------------------------------------- |
| team         | 1           | Deprecated in API v2. Replaced with `team_name`    |
| members      | 1           | Deprecated in API v2. Replaced with `team_members` |
| team_id      | 2           | Returns the unique ID of the specified team        |
| team_name    | 2           | The name of the team                               |
| team_members | 2           | A comma separated list of BZIDs of team members    |

### Legacy Support

API v1 JSON fields are returned in v2 in order to provide backwards compatability for any BZFlag servers that still use League Overseer 1.1.x. Legacy fields will be removed from API v2 in the near future.