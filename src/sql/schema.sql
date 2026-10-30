-- FNJ 이미지 Check 회원 / 선택 테이블

CREATE TABLE IF NOT EXISTS `NAS_MEMBER` (
  `mem_id`        int(11)      NOT NULL AUTO_INCREMENT,
  `login_id`      varchar(50)  NOT NULL,
  `passwd`        varchar(255) NOT NULL COMMENT 'password_hash()',
  `name`          varchar(50)  NOT NULL DEFAULT '',
  `email`         varchar(100) NOT NULL DEFAULT '',
  `is_admin`      tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1:총괄 어드민',
  `last_login_at` datetime     DEFAULT NULL,
  `created_at`    datetime     NOT NULL,
  `updated_at`    datetime     NOT NULL,
  `db_state`      char(1)      NOT NULL DEFAULT 'Y' COMMENT 'Y:사용 N:중지 D:삭제',
  PRIMARY KEY (`mem_id`),
  UNIQUE KEY `uk_login_id` (`login_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 회원이 선택한 썸네일. 재적재 시 img_id 가 바뀌므로 path_hash 로 연결
CREATE TABLE IF NOT EXISTS `NAS_MEMBER_PICK` (
  `mem_id`     int(11)    NOT NULL,
  `path_hash`  binary(20) NOT NULL,
  `img_id`     bigint(20) NOT NULL COMMENT '선택 당시 img_id (참고용)',
  `created_at` datetime   NOT NULL,
  PRIMARY KEY (`mem_id`, `path_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
