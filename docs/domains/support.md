# Support domain

`Support` presents provider/admin support chat and ticket workflows through
`SupportChatController`: thread creation, message/read operations, ticket
approval/rejection/end, and provider internal starts.

It depends on Chat for persistence/access/events, Media for private attachment,
Notification for in-app signals, Provider for actor/tenant/menu/document state,
and Audit for sensitive access/mutations where implemented.

Support state is not authorized by a route ID alone. The application must
resolve the authenticated actor, tenant, participant, requested menu, and
thread/ticket lifecycle. Attachment validation currently accepts only bounded
image formats/sizes; arbitrary file uploads are not part of the chat contract.
