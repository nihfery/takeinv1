[CmdletBinding()]
param(
    [switch] $Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$laravelRoot = (Resolve-Path -LiteralPath (Join-Path $repositoryRoot 'backend\laravel-core')).Path
$repositoryBoundary = $repositoryRoot + [IO.Path]::DirectorySeparatorChar
$laravelBoundary = $laravelRoot + [IO.Path]::DirectorySeparatorChar
$utf8WithoutBom = [Text.UTF8Encoding]::new($false)

$moduleMappings = @(
    # Identity
    @{ Source = 'app/Models/User.php'; Destination = 'app/Modules/Identity/Infrastructure/Persistence/Models/User.php' },
    @{ Source = 'app/Models/AdminProfile.php'; Destination = 'app/Modules/Identity/Infrastructure/Persistence/Models/AdminProfile.php' },
    @{ Source = 'app/Http/Controllers/Api/AuthController.php'; Destination = 'app/Modules/Identity/Presentation/Api/Auth/AuthController.php' },
    @{ Source = 'app/Http/Controllers/Auth/UnifiedLoginController.php'; Destination = 'app/Modules/Identity/Presentation/Web/Auth/UnifiedLoginController.php' },

    # Customer
    @{ Source = 'app/Models/CustomerProfile.php'; Destination = 'app/Modules/Customer/Infrastructure/Persistence/Models/CustomerProfile.php' },
    @{ Source = 'app/Models/CustomerActivity.php'; Destination = 'app/Modules/Customer/Infrastructure/Persistence/Models/CustomerActivity.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/ProfileController.php'; Destination = 'app/Modules/Customer/Presentation/Api/Customer/ProfileController.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/ActivityController.php'; Destination = 'app/Modules/Customer/Presentation/Api/Customer/ActivityController.php' },
    @{ Source = 'app/Http/Controllers/Provider/CustomerController.php'; Destination = 'app/Modules/Customer/Presentation/Web/Provider/CustomerController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/CustomerController.php'; Destination = 'app/Modules/Customer/Presentation/Api/Admin/CustomerController.php' },

    # Provider
    @{ Source = 'app/Models/ProviderProfile.php'; Destination = 'app/Modules/Provider/Infrastructure/Persistence/Models/ProviderProfile.php' },
    @{ Source = 'app/Models/ProviderRole.php'; Destination = 'app/Modules/Provider/Infrastructure/Persistence/Models/ProviderRole.php' },
    @{ Source = 'app/Models/ProviderRoleMenuPermission.php'; Destination = 'app/Modules/Provider/Infrastructure/Persistence/Models/ProviderRoleMenuPermission.php' },
    @{ Source = 'app/Services/SalonEligibilityService.php'; Destination = 'app/Modules/Provider/Application/Services/SalonEligibilityService.php' },
    @{ Source = 'app/Support/ProviderAccountScope.php'; Destination = 'app/Modules/Provider/Application/Support/ProviderAccountScope.php' },
    @{ Source = 'app/Support/ProviderMenuAccess.php'; Destination = 'app/Modules/Provider/Application/Support/ProviderMenuAccess.php' },
    @{ Source = 'app/Http/Controllers/Provider/ProfileController.php'; Destination = 'app/Modules/Provider/Presentation/Web/Provider/ProfileController.php' },
    @{ Source = 'app/Http/Controllers/Provider/DashboardController.php'; Destination = 'app/Modules/Provider/Presentation/Web/Provider/DashboardController.php' },
    @{ Source = 'app/Http/Controllers/Provider/RolePermissionController.php'; Destination = 'app/Modules/Provider/Presentation/Web/Provider/RolePermissionController.php' },
    @{ Source = 'app/Http/Controllers/Admin/ProviderController.php'; Destination = 'app/Modules/Provider/Presentation/Web/Admin/ProviderController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/ProviderController.php'; Destination = 'app/Modules/Provider/Presentation/Api/Admin/ProviderController.php' },
    @{ Source = 'app/Http/Controllers/Api/Provider/ProfileController.php'; Destination = 'app/Modules/Provider/Presentation/Api/Provider/ProfileController.php' },

    # Branch
    @{ Source = 'app/Models/ProviderBranch.php'; Destination = 'app/Modules/Branch/Infrastructure/Persistence/Models/ProviderBranch.php' },
    @{ Source = 'app/Http/Controllers/Provider/BranchController.php'; Destination = 'app/Modules/Branch/Presentation/Web/Provider/BranchController.php' },
    @{ Source = 'app/Http/Controllers/Api/Provider/BranchController.php'; Destination = 'app/Modules/Branch/Presentation/Api/Provider/BranchController.php' },

    # Catalog
    @{ Source = 'app/Models/Service.php'; Destination = 'app/Modules/Catalog/Infrastructure/Persistence/Models/Service.php' },
    @{ Source = 'app/Models/ServiceCategory.php'; Destination = 'app/Modules/Catalog/Infrastructure/Persistence/Models/ServiceCategory.php' },
    @{ Source = 'app/Http/Controllers/Provider/ServiceController.php'; Destination = 'app/Modules/Catalog/Presentation/Web/Provider/ServiceController.php' },
    @{ Source = 'app/Http/Controllers/Admin/ServiceController.php'; Destination = 'app/Modules/Catalog/Presentation/Web/Admin/ServiceController.php' },
    @{ Source = 'app/Http/Controllers/Admin/ServiceCategoryController.php'; Destination = 'app/Modules/Catalog/Presentation/Web/Admin/ServiceCategoryController.php' },
    @{ Source = 'app/Http/Controllers/Api/Provider/ServiceController.php'; Destination = 'app/Modules/Catalog/Presentation/Api/Provider/ServiceController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/ServiceController.php'; Destination = 'app/Modules/Catalog/Presentation/Api/Admin/ServiceController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/ServiceCategoryController.php'; Destination = 'app/Modules/Catalog/Presentation/Api/Admin/ServiceCategoryController.php' },
    @{ Source = 'app/Http/Controllers/Api/PublicCatalogController.php'; Destination = 'app/Modules/Catalog/Presentation/Api/Public/PublicCatalogController.php' },

    # Staff
    @{ Source = 'app/Models/ProviderStaff.php'; Destination = 'app/Modules/Staff/Infrastructure/Persistence/Models/ProviderStaff.php' },
    @{ Source = 'app/Models/StaffSkill.php'; Destination = 'app/Modules/Staff/Infrastructure/Persistence/Models/StaffSkill.php' },
    @{ Source = 'app/Models/StaffSchedule.php'; Destination = 'app/Modules/Staff/Infrastructure/Persistence/Models/StaffSchedule.php' },
    @{ Source = 'app/Http/Controllers/Provider/StaffController.php'; Destination = 'app/Modules/Staff/Presentation/Web/Provider/StaffController.php' },
    @{ Source = 'app/Http/Controllers/Api/Provider/StaffController.php'; Destination = 'app/Modules/Staff/Presentation/Api/Provider/StaffController.php' },

    # Booking
    @{ Source = 'app/Models/Booking.php'; Destination = 'app/Modules/Booking/Infrastructure/Persistence/Models/Booking.php' },
    @{ Source = 'app/Models/BookingParticipant.php'; Destination = 'app/Modules/Booking/Infrastructure/Persistence/Models/BookingParticipant.php' },
    @{ Source = 'app/Services/BookingFlowService.php'; Destination = 'app/Modules/Booking/Application/Services/BookingFlowService.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/BookingController.php'; Destination = 'app/Modules/Booking/Presentation/Api/Customer/BookingController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/BookingController.php'; Destination = 'app/Modules/Booking/Presentation/Api/Admin/BookingController.php' },
    @{ Source = 'app/Http/Controllers/Admin/BookingController.php'; Destination = 'app/Modules/Booking/Presentation/Web/Admin/BookingController.php' },
    @{ Source = 'app/Http/Controllers/Provider/BookingController.php'; Destination = 'app/Modules/Booking/Presentation/Web/Provider/BookingController.php' },
    @{ Source = 'app/Http/Controllers/Admin/CalendarController.php'; Destination = 'app/Modules/Booking/Presentation/Web/Admin/CalendarController.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/GraphqlController.php'; Destination = 'app/Modules/Booking/Presentation/Api/Customer/GraphqlController.php' },

    # Payment
    @{ Source = 'app/Models/Payment.php'; Destination = 'app/Modules/Payment/Infrastructure/Persistence/Models/Payment.php' },
    @{ Source = 'app/Models/PaymentGatewayTransaction.php'; Destination = 'app/Modules/Payment/Infrastructure/Persistence/Models/PaymentGatewayTransaction.php' },
    @{ Source = 'app/Services/MidtransService.php'; Destination = 'app/Modules/Payment/Infrastructure/Gateways/Midtrans/MidtransService.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/PaymentController.php'; Destination = 'app/Modules/Payment/Presentation/Api/Customer/PaymentController.php' },
    @{ Source = 'app/Http/Controllers/Api/MidtransNotificationController.php'; Destination = 'app/Modules/Payment/Presentation/Webhook/MidtransNotificationController.php' },

    # Subscription
    @{ Source = 'app/Models/SubscriptionPlan.php'; Destination = 'app/Modules/Subscription/Infrastructure/Persistence/Models/SubscriptionPlan.php' },
    @{ Source = 'app/Models/ProviderSubscription.php'; Destination = 'app/Modules/Subscription/Infrastructure/Persistence/Models/ProviderSubscription.php' },
    @{ Source = 'app/Services/ProviderEntitlementService.php'; Destination = 'app/Modules/Subscription/Application/Services/ProviderEntitlementService.php' },
    @{ Source = 'app/Http/Controllers/Api/Provider/SubscriptionController.php'; Destination = 'app/Modules/Subscription/Presentation/Api/Provider/SubscriptionController.php' },
    @{ Source = 'app/Console/Commands/GrantLegacySubscriptions.php'; Destination = 'app/Modules/Subscription/Console/Commands/GrantLegacySubscriptions.php' },

    # Promotion
    @{ Source = 'app/Models/Coupon.php'; Destination = 'app/Modules/Promotion/Infrastructure/Persistence/Models/Coupon.php' },
    @{ Source = 'app/Services/CouponService.php'; Destination = 'app/Modules/Promotion/Application/Services/CouponService.php' },
    @{ Source = 'app/Http/Controllers/Api/CouponValidationController.php'; Destination = 'app/Modules/Promotion/Presentation/Api/Public/CouponValidationController.php' },
    @{ Source = 'app/Http/Controllers/Admin/CouponController.php'; Destination = 'app/Modules/Promotion/Presentation/Web/Admin/CouponController.php' },
    @{ Source = 'app/Http/Controllers/Api/Admin/CouponController.php'; Destination = 'app/Modules/Promotion/Presentation/Api/Admin/CouponController.php' },

    # Review
    @{ Source = 'app/Models/BranchReview.php'; Destination = 'app/Modules/Review/Infrastructure/Persistence/Models/BranchReview.php' },
    @{ Source = 'app/Models/StaffReview.php'; Destination = 'app/Modules/Review/Infrastructure/Persistence/Models/StaffReview.php' },
    @{ Source = 'app/Http/Controllers/Api/Customer/ReviewController.php'; Destination = 'app/Modules/Review/Presentation/Api/Customer/ReviewController.php' },

    # Notification
    @{ Source = 'app/Models/AppNotification.php'; Destination = 'app/Modules/Notification/Infrastructure/Persistence/Models/AppNotification.php' },
    @{ Source = 'app/Services/AppNotificationService.php'; Destination = 'app/Modules/Notification/Application/Services/AppNotificationService.php' },
    @{ Source = 'app/Events/UserNotificationSent.php'; Destination = 'app/Modules/Notification/Domain/Events/UserNotificationSent.php' },
    @{ Source = 'app/Http/Controllers/NotificationController.php'; Destination = 'app/Modules/Notification/Presentation/Web/NotificationController.php' },

    # Chat
    @{ Source = 'app/Models/ChatThread.php'; Destination = 'app/Modules/Chat/Infrastructure/Persistence/Models/ChatThread.php' },
    @{ Source = 'app/Models/ChatMessage.php'; Destination = 'app/Modules/Chat/Infrastructure/Persistence/Models/ChatMessage.php' },
    @{ Source = 'app/Events/ChatMessageSent.php'; Destination = 'app/Modules/Chat/Domain/Events/ChatMessageSent.php' },
    @{ Source = 'app/Events/ChatThreadUpdated.php'; Destination = 'app/Modules/Chat/Domain/Events/ChatThreadUpdated.php' },
    @{ Source = 'app/Support/ChatMessagePresenter.php'; Destination = 'app/Modules/Chat/Presentation/Support/ChatMessagePresenter.php' },
    @{ Source = 'app/Support/ChatUnreadCounter.php'; Destination = 'app/Modules/Chat/Application/Support/ChatUnreadCounter.php' },

    # Support
    @{ Source = 'app/Http/Controllers/SupportChatController.php'; Destination = 'app/Modules/Support/Presentation/Web/SupportChatController.php' }
)

function Convert-PathToClassName {
    param([Parameter(Mandatory)][string] $RelativePath)

    $classPath = $RelativePath.Substring('app/'.Length, $RelativePath.Length - 'app/'.Length - '.php'.Length)
    return 'App\' + ($classPath -replace '/', '\')
}

function Get-ClassNamespace {
    param([Parameter(Mandatory)][string] $ClassName)

    return $ClassName.Substring(0, $ClassName.LastIndexOf('\'))
}

if ($moduleMappings.Count -ne 77) {
    throw "Expected 77 Section 10 mappings, found $($moduleMappings.Count)."
}

$seenSources = @{}
$seenDestinations = @{}
foreach ($mapping in $moduleMappings) {
    if ($seenSources.ContainsKey($mapping.Source)) {
        throw "Duplicate source mapping: $($mapping.Source)"
    }
    if ($seenDestinations.ContainsKey($mapping.Destination)) {
        throw "Duplicate destination mapping: $($mapping.Destination)"
    }
    $seenSources[$mapping.Source] = $true
    $seenDestinations[$mapping.Destination] = $true

    $sourcePath = [IO.Path]::GetFullPath((Join-Path $laravelRoot $mapping.Source))
    $destinationPath = [IO.Path]::GetFullPath((Join-Path $laravelRoot $mapping.Destination))
    if (-not $sourcePath.StartsWith($laravelBoundary, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Unsafe source mapping: $sourcePath"
    }
    if (-not $destinationPath.StartsWith($laravelBoundary, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Unsafe destination mapping: $destinationPath"
    }

    $sourceExists = Test-Path -LiteralPath $sourcePath -PathType Leaf
    $destinationExists = Test-Path -LiteralPath $destinationPath -PathType Leaf
    if ($sourceExists -eq $destinationExists) {
        throw "Expected exactly one existing path for $($mapping.Source) -> $($mapping.Destination)."
    }

    if ($Apply -and $sourceExists) {
        $sourceItem = Get-Item -LiteralPath $sourcePath -Force
        if (($sourceItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Refusing to relocate a reparse point: $sourcePath"
        }
        $destinationParent = Split-Path -Parent $destinationPath
        if (-not $destinationParent.StartsWith($laravelBoundary, [StringComparison]::OrdinalIgnoreCase)) {
            throw "Unsafe destination parent: $destinationParent"
        }
        if (-not (Test-Path -LiteralPath $destinationParent -PathType Container)) {
            New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
        }
        Move-Item -LiteralPath $sourcePath -Destination $destinationPath -ErrorAction Stop
    }
}

if ($Apply) {
    $candidateRoots = @($laravelRoot, (Join-Path $repositoryRoot 'tools\legacy'))
    $candidateFiles = foreach ($candidateRoot in $candidateRoots) {
        if (Test-Path -LiteralPath $candidateRoot -PathType Container) {
            Get-ChildItem -LiteralPath $candidateRoot -Recurse -File -Filter '*.php' | Where-Object {
                $_.FullName -notlike "$laravelRoot\vendor\*" -and
                $_.FullName -notlike "$laravelRoot\storage\framework\*" -and
                $_.FullName -notlike "$laravelRoot\database\migrations\*"
            }
        }
    }

    $replacementCount = 0
    foreach ($candidateFile in $candidateFiles) {
        $originalText = [IO.File]::ReadAllText($candidateFile.FullName)
        $updatedText = $originalText
        foreach ($mapping in $moduleMappings) {
            $oldClass = Convert-PathToClassName $mapping.Source
            $newClass = Convert-PathToClassName $mapping.Destination
            $updatedText = $updatedText.Replace($oldClass, $newClass)
        }

        $destinationRelative = $candidateFile.FullName.Substring($laravelBoundary.Length) -replace '\\', '/'
        $destinationMapping = $moduleMappings | Where-Object { $_.Destination -eq $destinationRelative } | Select-Object -First 1
        if ($null -ne $destinationMapping) {
            $oldClass = Convert-PathToClassName $destinationMapping.Source
            $newClass = Convert-PathToClassName $destinationMapping.Destination
            $oldNamespace = Get-ClassNamespace $oldClass
            $newNamespace = Get-ClassNamespace $newClass
            $oldDeclaration = "namespace $oldNamespace;"
            $newDeclaration = "namespace $newNamespace;"
            if (-not $updatedText.Contains($oldDeclaration) -and -not $updatedText.Contains($newDeclaration)) {
                throw "Namespace declaration was not found in $destinationRelative"
            }
            $updatedText = $updatedText.Replace($oldDeclaration, $newDeclaration)

            $requiredImports = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
            if ($destinationMapping.Source.StartsWith('app/Http/Controllers/')) {
                if ($updatedText -match 'class\s+\w+\s+extends\s+Controller' -and
                    -not $updatedText.Contains('use App\Http\Controllers\Controller;')) {
                    [void] $requiredImports.Add('App\Http\Controllers\Controller')
                }
                if ($updatedText -match 'class\s+\w+\s+extends\s+ApiController' -and
                    -not $updatedText.Contains('use App\Http\Controllers\Api\ApiController;')) {
                    [void] $requiredImports.Add('App\Http\Controllers\Api\ApiController')
                }
            }

            # Classes in one legacy namespace could reference each other without
            # imports. Once they belong to different modules those implicit
            # references must become explicit imports.
            foreach ($dependencyMapping in $moduleMappings) {
                if ($dependencyMapping.Source -eq $destinationMapping.Source) {
                    continue
                }
                $dependencyOldClass = Convert-PathToClassName $dependencyMapping.Source
                if ((Get-ClassNamespace $dependencyOldClass) -ne $oldNamespace) {
                    continue
                }
                $dependencyShortName = $dependencyOldClass.Substring($dependencyOldClass.LastIndexOf('\') + 1)
                $dependencyNewClass = Convert-PathToClassName $dependencyMapping.Destination
                if ($updatedText -match ('(?<![A-Za-z0-9_])' + [regex]::Escape($dependencyShortName) + '(?![A-Za-z0-9_])') -and
                    -not $updatedText.Contains("use $dependencyNewClass;")) {
                    [void] $requiredImports.Add($dependencyNewClass)
                }
            }

            if ($requiredImports.Count -gt 0) {
                $newline = if ($updatedText.Contains("`r`n")) { "`r`n" } else { "`n" }
                $namespaceAnchor = $newDeclaration + $newline + $newline
                if (-not $updatedText.Contains($namespaceAnchor)) {
                    throw "Could not find import anchor in $destinationRelative"
                }
                $importBlock = (($requiredImports | Sort-Object) | ForEach-Object { "use $_;" }) -join $newline
                $updatedText = $updatedText.Replace(
                    $namespaceAnchor,
                    $namespaceAnchor + $importBlock + $newline
                )
            }
        }

        if ($updatedText -cne $originalText) {
            [IO.File]::WriteAllText($candidateFile.FullName, $updatedText, $utf8WithoutBom)
            $replacementCount++
        }
    }

    Write-Output "Relocated $($moduleMappings.Count) mapped classes."
    Write-Output "Updated namespace/import references in $replacementCount PHP files."
}

$missingDestinations = @()
$remainingSources = @()
foreach ($mapping in $moduleMappings) {
    $sourcePath = [IO.Path]::GetFullPath((Join-Path $laravelRoot $mapping.Source))
    $destinationPath = [IO.Path]::GetFullPath((Join-Path $laravelRoot $mapping.Destination))
    if (Test-Path -LiteralPath $sourcePath -PathType Leaf) {
        $remainingSources += $mapping.Source
    }
    if (-not (Test-Path -LiteralPath $destinationPath -PathType Leaf)) {
        $missingDestinations += $mapping.Destination
    }
}

if ($Apply -and ($remainingSources.Count -gt 0 -or $missingDestinations.Count -gt 0)) {
    throw "Relocation verification failed: $($remainingSources.Count) sources remain; $($missingDestinations.Count) destinations missing."
}

Write-Output "Mappings verified: $($moduleMappings.Count)."
Write-Output "Remaining source files: $($remainingSources.Count)."
Write-Output "Missing destination files: $($missingDestinations.Count)."
