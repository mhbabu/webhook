<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property-read \App\Models\User|null $agent
 * @property-read \App\Models\Platform|null $platform
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentPlatformRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentPlatformRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentPlatformRole query()
 */
	class AgentPlatformRole extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $agent_id
 * @property string|null $platform
 * @property string|null $trace_id
 * @property string $started_at
 * @property string|null $end_at
 * @property int|null $last_message_id
 * @property int|null $ended_by
 * @property int|null $wrap_up_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $agent
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\Message|null $lastMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\WrapUpConversation|null $wrapUp
 * @method static \Database\Factories\ConversationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereEndedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereLastMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereTraceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereWrapUpId($value)
 */
	class Conversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $profile_photo
 * @property string|null $platform_user_id
 * @property int $platform_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Conversation|null $conversation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messagesReceived
 * @property-read int|null $messages_received_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messagesSent
 * @property-read int|null $messages_sent_count
 * @property-read \App\Models\Platform $platform
 * @method static \Database\Factories\CustomerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePlatformId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePlatformUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereProfilePhoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 */
	class Customer extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $sender_id
 * @property string|null $sender_type
 * @property int|null $receiver_id
 * @property string|null $receiver_type
 * @property string $type
 * @property string|null $content
 * @property string $direction
 * @property string|null $read_at
 * @property string|null $read_by
 * @property string|null $platform_message_id
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MessageAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\Conversation $conversation
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $receiver
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $sender
 * @method static \Database\Factories\MessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message wherePlatformMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReceiverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReceiverType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSenderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 */
	class Message extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $message_id
 * @property string|null $type
 * @property string|null $path
 * @property string|null $mime
 * @property string|null $size
 * @property string|null $attachment_id
 * @property bool $is_available
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Message $message
 * @method static \Database\Factories\MessageAttachmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereAttachmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereUpdatedAt($value)
 */
	class MessageAttachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $otp
 * @property string $expire_at
 * @property string|null $created_at
 * @property string|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereExpireAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereOtp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpVerification whereUserId($value)
 */
	class OtpVerification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform whereUpdatedAt($value)
 */
	class Platform extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuickReply whereUpdatedAt($value)
 */
	class QuickReply extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $parent_role_id
 * @property int $child_role_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy whereChildRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy whereParentRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoleHierarchy whereUpdatedAt($value)
 */
	class RoleHierarchy extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $employee_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $mobile
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property int $is_verified
 * @property string|null $password
 * @property string $current_status
 * @property int $max_limit
 * @property int $current_limit
 * @property string $account_status
 * @property int $is_request
 * @property int $is_password_updated
 * @property int|null $role_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \App\Enums\UserStatus $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messagesReceived
 * @property-read int|null $messages_received_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messagesSent
 * @property-read int|null $messages_sent_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Platform> $platforms
 * @property-read int|null $platforms_count
 * @property-read \Spatie\Permission\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \App\Models\UserStatusUpdate|null $userStatusInfo
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAccountStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsPasswordUpdated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMaxLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent implements \Spatie\MediaLibrary\HasMedia {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuickReply whereUserId($value)
 */
	class UserQuickReply extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property \App\Enums\UserStatus|null $status
 * @property string|null $break_request_status
 * @property string|null $reason
 * @property string|null $request_at
 * @property string|null $approved_at
 * @property int|null $approved_by
 * @property string $changed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereBreakRequestStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereRequestAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStatusUpdate whereUserId($value)
 */
	class UserStatusUpdate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\WrapUpConversationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WrapUpConversation whereUpdatedAt($value)
 */
	class WrapUpConversation extends \Eloquent {}
}

