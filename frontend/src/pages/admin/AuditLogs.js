import React, { useState, useEffect } from 'react';
import { Table, Card, Input, Select, DatePicker, Tag, Space } from 'antd';
import { SearchOutlined } from '@ant-design/icons';
import { useAuth } from '../../contexts/AuthContext';
import axios from 'axios';
import moment from 'moment';

const { Option } = Select;
const { RangePicker } = DatePicker;

const AuditLogs = () => {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({ current: 1, pageSize: 50, total: 0 });
  const [filters, setFilters] = useState({});

  useEffect(() => {
    fetchLogs();
  }, [pagination.current, pagination.pageSize, filters]);

  const fetchLogs = async () => {
    try {
      setLoading(true);
      const params = {
        page: pagination.current,
        per_page: pagination.pageSize,
        ...filters,
      };

      const response = await axios.get('/api/admin/audit-logs', { params });
      setLogs(response.data.data);
      setPagination(prev => ({
        ...prev,
        total: response.data.total,
      }));
    } catch (error) {
      console.error('Failed to fetch audit logs:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleTableChange = (paginationConfig) => {
    setPagination(prev => ({
      ...prev,
      current: paginationConfig.current,
      pageSize: paginationConfig.pageSize,
    }));
  };

  const getActionColor = (action) => {
    const colors = {
      login: 'green',
      logout: 'blue',
      order_created: 'purple',
      order_updated: 'orange',
      order_approved: 'cyan',
      user_created: 'geekblue',
      user_updated: 'volcano',
      user_deleted: 'red',
      settings_updated: 'magenta',
    };
    return colors[action] || 'default';
  };

  const getActionText = (action) => {
    return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  };

  const columns = [
    {
      title: 'Timestamp',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 150,
      render: (date) => moment(date).format('YYYY-MM-DD HH:mm:ss'),
    },
    {
      title: 'User',
      dataIndex: ['user', 'name'],
      key: 'user',
      width: 120,
    },
    {
      title: 'Action',
      dataIndex: 'action',
      key: 'action',
      width: 150,
      render: (action) => (
        <Tag color={getActionColor(action)}>
          {getActionText(action)}
        </Tag>
      ),
    },
    {
      title: 'Model',
      dataIndex: 'model_type',
      key: 'model_type',
      width: 120,
      render: (modelType) => modelType ? modelType.split('\\').pop() : '-',
    },
    {
      title: 'Model ID',
      dataIndex: 'model_id',
      key: 'model_id',
      width: 80,
    },
    {
      title: 'IP Address',
      dataIndex: 'ip_address',
      key: 'ip_address',
      width: 120,
    },
    {
      title: 'Details',
      key: 'details',
      render: (_, record) => {
        if (record.new_values) {
          const details = [];
          if (record.new_values.order_number) {
            details.push(`Order: ${record.new_values.order_number}`);
          }
          if (record.new_values.name) {
            details.push(`Name: ${record.new_values.name}`);
          }
          if (record.new_values.role) {
            details.push(`Role: ${record.new_values.role}`);
          }
          return details.length > 0 ? details.join(', ') : '-';
        }
        return '-';
      },
    },
  ];

  return (
    <div style={{ padding: '24px' }}>
      <Card title="Audit Logs">
        {/* Filters */}
        <div style={{ marginBottom: '16px', display: 'flex', gap: '16px', flexWrap: 'wrap' }}>
          <Input
            placeholder="Search action"
            prefix={<SearchOutlined />}
            style={{ width: 200 }}
            onChange={(e) => setFilters({ ...filters, action: e.target.value })}
          />
          
          <Select
            placeholder="Filter by User"
            style={{ width: 200 }}
            allowClear
            showSearch
            filterOption={(input, option) =>
              option.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
            }
            onChange={(value) => setFilters({ ...filters, user_id: value })}
          >
            {/* This would need to be populated with user list */}
          </Select>
          
          <RangePicker
            placeholder={['Start Date', 'End Date']}
            onChange={(dates) => {
              if (dates) {
                setFilters({
                  ...filters,
                  date_from: dates[0].format('YYYY-MM-DD'),
                  date_to: dates[1].format('YYYY-MM-DD'),
                });
              } else {
                const { date_from, date_to, ...rest } = filters;
                setFilters(rest);
              }
            }}
          />
        </div>

        <Table
          columns={columns}
          dataSource={logs}
          rowKey="id"
          pagination={pagination}
          onChange={handleTableChange}
          loading={loading}
          size="small"
        />
      </Card>
    </div>
  );
};

export default AuditLogs;
