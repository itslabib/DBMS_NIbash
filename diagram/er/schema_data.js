const fullSchema = {
    "apartments": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "apt_number",
            "Key": ""
        },
        {
            "Field": "floor_number",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        }
    ],
    "apartment_assignments": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "role",
            "Key": ""
        },
        {
            "Field": "start_date",
            "Key": ""
        },
        {
            "Field": "end_date",
            "Key": ""
        },
        {
            "Field": "monthly_rent",
            "Key": ""
        },
        {
            "Field": "is_active",
            "Key": ""
        }
    ],
    "bills": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "bill_number",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "resident_id",
            "Key": ""
        },
        {
            "Field": "month",
            "Key": ""
        },
        {
            "Field": "year",
            "Key": ""
        },
        {
            "Field": "issue_date",
            "Key": ""
        },
        {
            "Field": "due_date",
            "Key": ""
        },
        {
            "Field": "subtotal",
            "Key": ""
        },
        {
            "Field": "discount",
            "Key": ""
        },
        {
            "Field": "tax",
            "Key": ""
        },
        {
            "Field": "paid_amount",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "notes",
            "Key": ""
        },
        {
            "Field": "created_by",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "updated_at",
            "Key": ""
        },
        {
            "Field": "total_amount",
            "Key": ""
        }
    ],
    "bill_items": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "bill_id",
            "Key": ""
        },
        {
            "Field": "utility_type_id",
            "Key": ""
        },
        {
            "Field": "item_name",
            "Key": ""
        },
        {
            "Field": "quantity",
            "Key": ""
        },
        {
            "Field": "unit_price",
            "Key": ""
        },
        {
            "Field": "amount",
            "Key": ""
        },
        {
            "Field": "tax_amount",
            "Key": ""
        }
    ],
    "buildings": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "building_number",
            "Key": ""
        },
        {
            "Field": "building_name",
            "Key": ""
        },
        {
            "Field": "address",
            "Key": ""
        },
        {
            "Field": "area",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "latitude",
            "Key": ""
        },
        {
            "Field": "longitude",
            "Key": ""
        }
    ],
    "building_managers": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "role",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "cctv_alerts": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "capture_id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "alert_type",
            "Key": ""
        },
        {
            "Field": "message",
            "Key": ""
        },
        {
            "Field": "is_sent",
            "Key": ""
        },
        {
            "Field": "sent_at",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "cctv_captures": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "camera_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "image_path",
            "Key": ""
        },
        {
            "Field": "detection_type",
            "Key": ""
        },
        {
            "Field": "matched_confidence",
            "Key": ""
        },
        {
            "Field": "captured_at",
            "Key": ""
        },
        {
            "Field": "is_reviewed",
            "Key": ""
        },
        {
            "Field": "face_hash",
            "Key": ""
        }
    ],
    "cctv_devices": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "camera_name",
            "Key": ""
        },
        {
            "Field": "ip_address",
            "Key": ""
        },
        {
            "Field": "location_description",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        }
    ],
    "community_categories": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "category_name",
            "Key": ""
        }
    ],
    "community_comments": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "post_id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "parent_comment_id",
            "Key": ""
        },
        {
            "Field": "content",
            "Key": ""
        },
        {
            "Field": "image_path",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "community_posts": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "category_id",
            "Key": ""
        },
        {
            "Field": "title",
            "Key": ""
        },
        {
            "Field": "content",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "is_pinned",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "updated_at",
            "Key": ""
        }
    ],
    "community_post_images": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "post_id",
            "Key": ""
        },
        {
            "Field": "image_path",
            "Key": ""
        }
    ],
    "coupons": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "code",
            "Key": ""
        },
        {
            "Field": "discount_percent",
            "Key": ""
        },
        {
            "Field": "valid_until",
            "Key": ""
        },
        {
            "Field": "max_uses",
            "Key": ""
        },
        {
            "Field": "used_count",
            "Key": ""
        },
        {
            "Field": "target_user_id",
            "Key": ""
        },
        {
            "Field": "is_active",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "emergency_contacts": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_profile_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "title",
            "Key": ""
        },
        {
            "Field": "phone_number",
            "Key": ""
        },
        {
            "Field": "email",
            "Key": ""
        },
        {
            "Field": "contact_type",
            "Key": ""
        }
    ],
    "entry_logs": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "visit_id",
            "Key": ""
        },
        {
            "Field": "entry_time",
            "Key": ""
        },
        {
            "Field": "exit_time",
            "Key": ""
        },
        {
            "Field": "entry_method",
            "Key": ""
        },
        {
            "Field": "verification_score",
            "Key": ""
        },
        {
            "Field": "gate_terminal_id",
            "Key": ""
        }
    ],
    "family_members": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "primary_user_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "member_name",
            "Key": ""
        },
        {
            "Field": "relation",
            "Key": ""
        },
        {
            "Field": "dob",
            "Key": ""
        },
        {
            "Field": "occupation",
            "Key": ""
        },
        {
            "Field": "phone_number",
            "Key": ""
        }
    ],
    "guests": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "full_name",
            "Key": ""
        },
        {
            "Field": "phone_number",
            "Key": ""
        },
        {
            "Field": "nid_passport_no",
            "Key": ""
        },
        {
            "Field": "face_descriptor",
            "Key": ""
        },
        {
            "Field": "blacklisted",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "guest_vehicles": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "visit_id",
            "Key": ""
        },
        {
            "Field": "plate_number",
            "Key": ""
        },
        {
            "Field": "vehicle_type",
            "Key": ""
        },
        {
            "Field": "entry_photo_path",
            "Key": ""
        }
    ],
    "notifications": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "title",
            "Key": ""
        },
        {
            "Field": "message",
            "Key": ""
        },
        {
            "Field": "link",
            "Key": ""
        },
        {
            "Field": "is_read",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "parking_details": [
        {
            "Field": "listing_id",
            "Key": ""
        },
        {
            "Field": "vehicle_type",
            "Key": ""
        },
        {
            "Field": "parking_length",
            "Key": ""
        },
        {
            "Field": "parking_width",
            "Key": ""
        },
        {
            "Field": "measurement_unit",
            "Key": ""
        }
    ],
    "parking_requests": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "slot_id",
            "Key": ""
        },
        {
            "Field": "requester_id",
            "Key": ""
        },
        {
            "Field": "target_resident_id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "start_time",
            "Key": ""
        },
        {
            "Field": "end_time",
            "Key": ""
        },
        {
            "Field": "license_plate",
            "Key": ""
        },
        {
            "Field": "purpose",
            "Key": ""
        },
        {
            "Field": "for_whom",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "overstay_notified",
            "Key": ""
        }
    ],
    "parking_slots": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "slot_number",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "floor_level",
            "Key": ""
        },
        {
            "Field": "current_status",
            "Key": ""
        },
        {
            "Field": "license_plate",
            "Key": ""
        },
        {
            "Field": "temporary_name",
            "Key": ""
        },
        {
            "Field": "temporary_until",
            "Key": ""
        },
        {
            "Field": "temporary_plate",
            "Key": ""
        }
    ],
    "payments": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "bill_id",
            "Key": ""
        },
        {
            "Field": "transaction_id",
            "Key": ""
        },
        {
            "Field": "amount_paid",
            "Key": ""
        },
        {
            "Field": "payment_method",
            "Key": ""
        },
        {
            "Field": "payment_status",
            "Key": ""
        },
        {
            "Field": "payment_date",
            "Key": ""
        }
    ],
    "personal_messages": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "sender_id",
            "Key": ""
        },
        {
            "Field": "receiver_id",
            "Key": ""
        },
        {
            "Field": "message",
            "Key": ""
        },
        {
            "Field": "is_read",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "provider_bookings": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "provider_id",
            "Key": ""
        },
        {
            "Field": "resident_id",
            "Key": ""
        },
        {
            "Field": "booking_date",
            "Key": ""
        },
        {
            "Field": "time_slot",
            "Key": ""
        },
        {
            "Field": "end_time",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "amount",
            "Key": ""
        }
    ],
    "provider_locations": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "provider_id",
            "Key": ""
        },
        {
            "Field": "latitude",
            "Key": ""
        },
        {
            "Field": "longitude",
            "Key": ""
        },
        {
            "Field": "address",
            "Key": ""
        }
    ],
    "provider_reviews": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "provider_id",
            "Key": ""
        },
        {
            "Field": "resident_id",
            "Key": ""
        },
        {
            "Field": "rating",
            "Key": ""
        },
        {
            "Field": "review_text",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "provider_subscription_plans": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "duration_months",
            "Key": ""
        },
        {
            "Field": "plan_name",
            "Key": ""
        },
        {
            "Field": "price",
            "Key": ""
        },
        {
            "Field": "save_amount",
            "Key": ""
        }
    ],
    "rental_images": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "listing_id",
            "Key": ""
        },
        {
            "Field": "image_path",
            "Key": ""
        },
        {
            "Field": "image_category",
            "Key": ""
        }
    ],
    "rental_listings": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "owner_id",
            "Key": ""
        },
        {
            "Field": "custom_title",
            "Key": ""
        },
        {
            "Field": "description",
            "Key": ""
        },
        {
            "Field": "rent_amount",
            "Key": ""
        },
        {
            "Field": "total_bedrooms",
            "Key": ""
        },
        {
            "Field": "floor_number",
            "Key": ""
        },
        {
            "Field": "washrooms",
            "Key": ""
        },
        {
            "Field": "balconies",
            "Key": ""
        },
        {
            "Field": "verification_doc_path",
            "Key": ""
        },
        {
            "Field": "is_verified",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "rental_type",
            "Key": ""
        }
    ],
    "resident_vehicles": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "plate_number",
            "Key": ""
        },
        {
            "Field": "vehicle_model",
            "Key": ""
        },
        {
            "Field": "vehicle_color",
            "Key": ""
        },
        {
            "Field": "rfid_tag_no",
            "Key": ""
        }
    ],
    "roles": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "role_name",
            "Key": ""
        }
    ],
    "service_categories": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "category_name",
            "Key": ""
        },
        {
            "Field": "icon_name",
            "Key": ""
        }
    ],
    "service_providers": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "category_id",
            "Key": ""
        },
        {
            "Field": "building_id",
            "Key": ""
        },
        {
            "Field": "name",
            "Key": ""
        },
        {
            "Field": "phone",
            "Key": ""
        },
        {
            "Field": "email",
            "Key": ""
        },
        {
            "Field": "website_url",
            "Key": ""
        },
        {
            "Field": "pricing_details",
            "Key": ""
        },
        {
            "Field": "nid_number",
            "Key": ""
        },
        {
            "Field": "image_path",
            "Key": ""
        },
        {
            "Field": "rating",
            "Key": ""
        },
        {
            "Field": "availability_schedule",
            "Key": ""
        },
        {
            "Field": "is_active",
            "Key": ""
        },
        {
            "Field": "address",
            "Key": ""
        },
        {
            "Field": "latitude",
            "Key": ""
        },
        {
            "Field": "longitude",
            "Key": ""
        },
        {
            "Field": "default_pricing",
            "Key": ""
        },
        {
            "Field": "coverage_radius",
            "Key": ""
        },
        {
            "Field": "is_subscribed",
            "Key": ""
        }
    ],
    "service_requests": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "owner_id",
            "Key": ""
        },
        {
            "Field": "issue_title",
            "Key": ""
        },
        {
            "Field": "description",
            "Key": ""
        },
        {
            "Field": "priority",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "assigned_provider_id",
            "Key": ""
        },
        {
            "Field": "rating",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "updated_at",
            "Key": ""
        }
    ],
    "subscriptions": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "subscriber_type",
            "Key": ""
        },
        {
            "Field": "subscriber_id",
            "Key": ""
        },
        {
            "Field": "plan_id",
            "Key": ""
        },
        {
            "Field": "duration_months",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "started_at",
            "Key": ""
        },
        {
            "Field": "expires_at",
            "Key": ""
        },
        {
            "Field": "assigned_by_admin",
            "Key": ""
        },
        {
            "Field": "notes",
            "Key": ""
        },
        {
            "Field": "tran_id",
            "Key": ""
        },
        {
            "Field": "payment_key",
            "Key": ""
        },
        {
            "Field": "payment_verified_at",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        },
        {
            "Field": "updated_at",
            "Key": ""
        }
    ],
    "subscription_plans": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "plan_name",
            "Key": ""
        },
        {
            "Field": "price_monthly",
            "Key": ""
        },
        {
            "Field": "max_residents",
            "Key": ""
        },
        {
            "Field": "max_cameras",
            "Key": ""
        },
        {
            "Field": "has_cctv",
            "Key": ""
        },
        {
            "Field": "has_analytics",
            "Key": ""
        },
        {
            "Field": "has_ai_chatbot",
            "Key": ""
        },
        {
            "Field": "description",
            "Key": ""
        },
        {
            "Field": "is_active",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "users": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "username",
            "Key": ""
        },
        {
            "Field": "email",
            "Key": ""
        },
        {
            "Field": "password",
            "Key": ""
        },
        {
            "Field": "role_id",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        },
        {
            "Field": "is_verified",
            "Key": ""
        },
        {
            "Field": "verification_code",
            "Key": ""
        },
        {
            "Field": "created_at",
            "Key": ""
        }
    ],
    "user_profiles": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "user_id",
            "Key": ""
        },
        {
            "Field": "full_name",
            "Key": ""
        },
        {
            "Field": "phone",
            "Key": ""
        },
        {
            "Field": "nid",
            "Key": ""
        },
        {
            "Field": "dob",
            "Key": ""
        },
        {
            "Field": "occupation",
            "Key": ""
        },
        {
            "Field": "permanent_address",
            "Key": ""
        },
        {
            "Field": "profile_image",
            "Key": ""
        },
        {
            "Field": "face_descriptor",
            "Key": ""
        },
        {
            "Field": "is_verified",
            "Key": ""
        },
        {
            "Field": "blood_group",
            "Key": ""
        },
        {
            "Field": "address",
            "Key": ""
        },
        {
            "Field": "latitude",
            "Key": ""
        },
        {
            "Field": "longitude",
            "Key": ""
        }
    ],
    "utility_types": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "utility_name",
            "Key": ""
        },
        {
            "Field": "provider_api_url",
            "Key": ""
        },
        {
            "Field": "is_fixed_rate",
            "Key": ""
        }
    ],
    "visit_requests": [
        {
            "Field": "id",
            "Key": ""
        },
        {
            "Field": "guest_id",
            "Key": ""
        },
        {
            "Field": "resident_id",
            "Key": ""
        },
        {
            "Field": "apt_id",
            "Key": ""
        },
        {
            "Field": "purpose",
            "Key": ""
        },
        {
            "Field": "digital_pass_code",
            "Key": ""
        },
        {
            "Field": "status",
            "Key": ""
        }
    ]
};
